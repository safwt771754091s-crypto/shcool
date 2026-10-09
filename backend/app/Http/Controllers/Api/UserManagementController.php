<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Permission\GlobalTeam;
use App\Support\Permission\Roles;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff account administration: create a user inside an organization and grant
 * them a tenant-scoped role.
 *
 * A school manager, directorate/governorate admin or the platform owner can
 * create the accounts they are responsible for. Only tenant-scoped roles may be
 * granted here; platform-wide roles (owner, super_admin, minister, ...) are
 * deliberately out of reach to prevent privilege escalation.
 */
class UserManagementController extends Controller
{
    /**
     * Roles a manager may grant. Excludes every global role.
     *
     * @var list<string>
     */
    protected const ASSIGNABLE_ROLES = [
        Roles::SCHOOL_MANAGER,
        Roles::BRANCH_MANAGER,
        Roles::VICE_PRINCIPAL,
        Roles::TEACHER,
        Roles::TEACHER_ASSISTANT,
        Roles::STUDENT_AFFAIRS,
        Roles::ACCOUNTANT,
        Roles::SECRETARY,
        Roles::PARENT,
        Roles::STUDENT,
    ];

    public function __construct(
        protected TenantManager $tenants,
        protected PermissionRegistrar $registrar,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization($request, $request->integer('organization_id') ?: null);

        $users = User::query()
            ->forTenant($organization->getKey())
            ->orderBy('name')
            ->paginate(50);

        return response()->json(['data' => UserResource::collection($users)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
        ]);

        $organization = $this->resolveOrganization(
            $request,
            $validated['organization_id'] ?? null,
        );

        $user = DB::transaction(function () use ($validated, $organization): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'tenant_id' => $organization->getKey(),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->assignRole($user, $validated['role'], $organization);

            $user->organizations()->syncWithoutDetaching([
                $organization->getKey() => ['is_primary' => true],
            ]);

            return $user;
        });

        app(AuditLogger::class)->log(
            'users.created',
            $user,
            description: "Created {$validated['role']} account for {$organization->name}.",
            newValues: ['email' => $user->email, 'role' => $validated['role']],
        );

        return response()->json([
            'data' => new UserResource($user->load('roles')),
            'message' => 'تم إنشاء الحساب.',
        ], 201);
    }

    public function assignRoleToUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);

        $organization = $this->resolveOrganization($request, $user->tenant_id);

        $this->assignRole($user, $validated['role'], $organization);

        app(AuditLogger::class)->log(
            'users.role_assigned',
            $user,
            description: "Granted role {$validated['role']}.",
            newValues: ['role' => $validated['role']],
        );

        return response()->json([
            'data' => new UserResource($user->fresh()->load('roles')),
            'message' => 'تم إسناد الدور.',
        ]);
    }

    /**
     * Resolve which organization the account belongs to. Platform owners may
     * target any organization explicitly; everyone else is locked to their own
     * tenant. The target must be a tenant root (school) or one of its branches.
     */
    protected function resolveOrganization(Request $request, ?int $organizationId): Organization
    {
        $user = $request->user();

        $organizationId ??= $this->tenants->tenantId() ?? $user->tenant_id;

        if ($organizationId === null) {
            throw ValidationException::withMessages([
                'organization_id' => ['حدّد الجهة التابعة للحساب.'],
            ]);
        }

        if (! $user->isPlatformAdmin() && (int) $organizationId !== (int) $user->tenant_id) {
            abort(403, 'لا يمكنك إدارة حسابات جهة أخرى.');
        }

        $organization = Organization::query()->findOrFail($organizationId);

        if (! in_array($organization->type, [Organization::TYPE_SCHOOL, Organization::TYPE_BRANCH], true)) {
            throw ValidationException::withMessages([
                'organization_id' => ['يجب أن تكون الحسابات ضمن مدرسة أو فرع.'],
            ]);
        }

        return $organization;
    }

    protected function assignRole(User $user, string $roleName, Organization $organization): void
    {
        // Roles are team-scoped, so point spatie at the target school before
        // granting the role (a branch shares its school's tenant).
        $teamId = $organization->type === Organization::TYPE_BRANCH
            ? ($organization->tenant_id ?? $organization->getKey())
            : $organization->getKey();

        $this->registrar->setPermissionsTeamId($teamId);
        $user->syncRoles([$roleName]);
        $this->registrar->setPermissionsTeamId(GlobalTeam::ID);
    }
}
