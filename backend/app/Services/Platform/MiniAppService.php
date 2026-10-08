<?php

namespace App\Services\Platform;

use App\Models\Organization;
use App\Models\Platform\MiniApp;
use App\Models\Platform\MiniAppInstance;
use App\Support\Competition\CompetitionScope;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Collection;

/**
 * Publishes mini-apps and rolls them up the hierarchy:
 * school apps -> directorate app -> governorate app -> platform.
 *
 * Publishing is a platform-level operation: an instance is addressed by
 * (app, organization), which is already unique, so the tenant scope is bypassed
 * to avoid mis-filtering directorate/ministry instances that are global.
 */
class MiniAppService
{
    public function __construct(protected TenantManager $tenants)
    {
    }

    public function createApp(array $attributes): MiniApp
    {
        return MiniApp::create($attributes);
    }

    /**
     * Publish an app to one organization. The scope is derived from the
     * organization type so it can never drift from the hierarchy.
     */
    public function publishTo(
        MiniApp $app,
        Organization $organization,
        array $settings = [],
        bool $enabled = true,
    ): MiniAppInstance {
        return $this->tenants->withoutTenancy(function () use ($app, $organization, $settings, $enabled) {
            $instance = MiniAppInstance::query()->firstOrNew([
                'mini_app_id' => $app->getKey(),
                'organization_id' => $organization->getKey(),
            ]);

            // Set tenant_id explicitly: directorate-level instances are global.
            $instance->forceFill([
                'tenant_id' => $this->tenantIdFor($organization),
                'scope_type' => CompetitionScope::fromOrganizationType($organization->type),
                'settings' => $settings,
                'is_enabled' => $enabled,
            ])->save();

            return $instance;
        });
    }

    protected function tenantIdFor(Organization $organization): ?int
    {
        if ($organization->type === Organization::TYPE_SCHOOL) {
            return $organization->getKey();
        }

        if ($organization->type === Organization::TYPE_BRANCH) {
            return $organization->tenant_id ?? $organization->school()?->getKey();
        }

        return null;
    }

    /**
     * Apps available to an organization, including those inherited from its
     * ancestors (a school sees directorate- and ministry-published apps too).
     *
     * @return Collection<int, MiniAppInstance>
     */
    public function availableFor(Organization $organization): Collection
    {
        $organizationIds = $organization->ancestors()
            ->pluck('id')
            ->push($organization->getKey())
            ->all();

        return $this->tenants->withoutTenancy(fn () => MiniAppInstance::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('is_enabled', true)
            ->with('app')
            ->get());
    }
}
