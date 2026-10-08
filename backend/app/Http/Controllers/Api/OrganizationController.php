<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(protected TenantManager $tenants)
    {
    }

    /**
     * List organizations. Non-platform users only ever see their own subtree.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Organization::query()->with('parent')->orderBy('level')->orderBy('name');

        if (! $user->isPlatformAdmin()) {
            $tenantId = $this->tenants->tenantId();

            if ($tenantId === null) {
                return response()->json(['data' => []]);
            }

            // The school itself plus everything beneath it (branches).
            $query->where(fn ($q) => $q
                ->whereKey($tenantId)
                ->orWhere('tenant_id', $tenantId));
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        return response()->json([
            'data' => OrganizationResource::collection($query->get()),
        ]);
    }

    /**
     * Create a child organization (e.g. a branch under a school).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['required', 'exists:organizations,id'],
            'type' => ['required', Rule::in(array_keys(Organization::LEVELS))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:organizations,code'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'settings' => ['nullable', 'array'],
        ]);

        $organization = new Organization($validated);
        $organization->parent_id = $validated['parent_id'];
        $organization->refreshHierarchy();
        $organization->save();

        // A school is its own tenant boundary.
        if ($organization->isSchool() && $organization->tenant_id === null) {
            $organization->forceFill(['tenant_id' => $organization->getKey()])->save();

            // Materialise the school's own roles so it can manage staff.
            app(\App\Support\Permission\RoleProvisioner::class)->provisionTenantRoles($organization);
        }

        return response()->json([
            'data' => new OrganizationResource($organization),
            'message' => 'تم إنشاء الجهة بنجاح.',
        ], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load(['parent', 'children']);

        return response()->json(['data' => new OrganizationResource($organization)]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $organization->update($validated);

        return response()->json(['data' => new OrganizationResource($organization)]);
    }

    /**
     * The full administrative tree (ministry -> ... -> branch), for the
     * ministry dashboard. Platform admins only.
     */
    public function tree(): JsonResponse
    {
        $roots = Organization::query()
            ->whereNull('parent_id')
            ->with('children.children.children.children')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => OrganizationResource::collection($roots)]);
    }
}
