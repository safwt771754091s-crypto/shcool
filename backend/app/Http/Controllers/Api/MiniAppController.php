<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Platform\MiniApp;
use App\Services\Platform\MiniAppService;
use App\Support\Competition\CompetitionScope;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App registry (سجل التطبيقات المصغّرة).
 *
 * Each school has an app, a directorate aggregates its schools, a governorate
 * aggregates its directorates, and the platform aggregates everything.
 */
class MiniAppController extends Controller
{
    public function __construct(
        protected MiniAppService $apps,
        protected TenantManager $tenants,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = MiniApp::query()->withCount('instances')->orderBy('name');

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'unique:mini_apps,slug'],
            'category' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'supported_scopes' => ['nullable', 'array'],
            'supported_scopes.*' => [Rule::in(CompetitionScope::LADDER)],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $app = $this->apps->createApp(array_merge($validated, [
            'created_by' => $request->user()->getKey(),
        ]));

        return response()->json([
            'data' => $app,
            'message' => 'تم إنشاء التطبيق.',
        ], 201);
    }

    /**
     * Publish an app to an organization (school / directorate / governorate).
     */
    public function publish(Request $request, MiniApp $app): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'settings' => ['nullable', 'array'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $organization = Organization::query()->findOrFail($validated['organization_id']);

        $instance = $this->apps->publishTo(
            $app,
            $organization,
            $validated['settings'] ?? [],
            $validated['is_enabled'] ?? true,
        );

        return response()->json([
            'data' => $instance->load('organization'),
            'message' => 'تم نشر التطبيق على الجهة.',
        ], 201);
    }

    /**
     * Apps available to the signed-in user's organization, including those
     * inherited from its ancestors.
     */
    public function available(Request $request): JsonResponse
    {
        $tenantId = $this->tenants->tenantId();

        if ($tenantId === null) {
            return response()->json(['data' => []]);
        }

        $organization = Organization::query()->findOrFail($tenantId);

        return response()->json([
            'data' => $this->apps->availableFor($organization),
        ]);
    }
}
