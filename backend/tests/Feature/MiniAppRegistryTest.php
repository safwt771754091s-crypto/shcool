<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Platform\MiniApp;
use App\Services\Platform\MiniAppService;
use App\Support\Competition\CompetitionScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniAppRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function hierarchy(): array
    {
        $ministry = Organization::factory()->ministry()->create();
        $governorate = Organization::factory()->governorate()->childOf($ministry)->create();
        $directorate = Organization::factory()->directorate()->childOf($governorate)->create();
        $school = Organization::factory()->tenant()->childOf($directorate)->create();

        return compact('ministry', 'governorate', 'directorate', 'school');
    }

    public function test_publishing_derives_scope_from_the_organization_type(): void
    {
        $h = $this->hierarchy();
        $service = app(MiniAppService::class);

        $app = $service->createApp(['name' => 'بوابة المدرسة', 'slug' => 'school-portal']);

        $schoolInstance = $service->publishTo($app, $h['school']);
        $this->assertSame(CompetitionScope::SCHOOL, $schoolInstance->scope_type);
        $this->assertSame($h['school']->id, $schoolInstance->tenant_id);

        $directorateInstance = $service->publishTo($app, $h['directorate']);
        $this->assertSame(CompetitionScope::DIRECTORATE, $directorateInstance->scope_type);
        $this->assertNull($directorateInstance->tenant_id);
    }

    public function test_a_school_inherits_apps_from_its_ancestors(): void
    {
        $h = $this->hierarchy();
        $service = app(MiniAppService::class);

        $ministryApp = $service->createApp(['name' => 'تطبيق الوزارة', 'slug' => 'ministry-app']);
        $schoolApp = $service->createApp(['name' => 'تطبيق المدرسة', 'slug' => 'school-app']);

        $service->publishTo($ministryApp, $h['ministry']);
        $service->publishTo($schoolApp, $h['school']);

        $available = $service->availableFor($h['school']);

        $this->assertCount(2, $available);
        $this->assertTrue($available->contains(fn ($i) => $i->app->slug === 'ministry-app'));
        $this->assertTrue($available->contains(fn ($i) => $i->app->slug === 'school-app'));
    }

    public function test_disabled_instances_are_hidden(): void
    {
        $h = $this->hierarchy();
        $service = app(MiniAppService::class);

        $app = $service->createApp(['name' => 'لعبة', 'slug' => 'game']);
        $service->publishTo($app, $h['school'], enabled: false);

        $this->assertCount(0, $service->availableFor($h['school']));
    }

    public function test_publishing_is_idempotent_per_organization(): void
    {
        $h = $this->hierarchy();
        $service = app(MiniAppService::class);

        $app = $service->createApp(['name' => 'سجل', 'slug' => 'registry']);

        $service->publishTo($app, $h['school'], ['theme' => 'dark']);
        $service->publishTo($app, $h['school'], ['theme' => 'light']);

        $this->assertSame(1, MiniApp::query()->where('slug', 'registry')->first()->instances()->count());
    }
}
