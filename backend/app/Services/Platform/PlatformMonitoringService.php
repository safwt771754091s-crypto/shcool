<?php

namespace App\Services\Platform;

use App\Models\Attendance\Attendance;
use App\Models\Organization;
use App\Models\Staff\Teacher;
use App\Models\Student\Student;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Support\Tenancy\TenantManager;

/**
 * Read-only, platform-wide roll-up for the Minister of Education
 * (وزير التربية) monitoring account.
 *
 * Every number is computed with tenancy bypassed so a single, unauthenticated
 * view shows the whole country: tree, per-school counts and finance totals.
 * This service never writes.
 */
class PlatformMonitoringService
{
    public function __construct(
        protected TenantManager $tenants,
        protected ReportService $reports,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return $this->tenants->withoutTenancy(function (): array {
            $schools = Organization::query()
                ->where('type', Organization::TYPE_SCHOOL)
                ->orderBy('name')
                ->get();

            $totals = [
                'governorates' => Organization::query()
                    ->where('type', Organization::TYPE_GOVERNORATE)->count(),
                'directorates' => Organization::query()
                    ->where('type', Organization::TYPE_DIRECTORATE)->count(),
                'schools' => $schools->count(),
                'branches' => Organization::query()
                    ->where('type', Organization::TYPE_BRANCH)->count(),
                'students' => Student::allTenants()->count(),
                'teachers' => Teacher::allTenants()->count(),
                'staff_accounts' => User::query()->whereNotNull('tenant_id')->count(),
                'attendance_records' => Attendance::allTenants()->count(),
            ];

            $perSchool = $schools->map(fn (Organization $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'students' => Student::allTenants()->where('tenant_id', $school->id)->count(),
                'teachers' => Teacher::allTenants()->where('tenant_id', $school->id)->count(),
            ])->all();

            return [
                'totals' => $totals,
                'per_school' => $perSchool,
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * The full administrative tree for the monitoring view (read-only).
     *
     * @return array{data: mixed}
     */
    public function tree(): array
    {
        return $this->tenants->withoutTenancy(function (): array {
            $roots = Organization::query()
                ->whereNull('parent_id')
                ->with('children.children.children.children')
                ->orderBy('name')
                ->get();

            return ['data' => \App\Http\Resources\OrganizationResource::collection($roots)];
        });
    }

    /**
     * The official schools roll-up report (same shape as the export).
     *
     * @return array<string, mixed>
     */
    public function schoolsReport(): array
    {
        return $this->reports->schoolsOverview();
    }
}
