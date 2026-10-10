<?php

namespace App\Services\Ai;

use App\Models\Attendance\Attendance;
use App\Models\Exam\Grade;
use App\Models\Finance\Invoice;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;

/**
 * Read-only data tools the AI agents may call (أدوات القراءة للوكلاء).
 *
 * Every tool runs inside the caller's tenant context, so the models' global
 * scopes guarantee a school can only ever read its own rows. Tools that expose
 * sensitive data additionally require a permission, and the caller's
 * permissions are enforced again in {@see allows()}.
 *
 * All handlers are strictly read-only: an agent can never mutate data.
 */
class AiToolbox
{
    /**
     * Roles that may only ever see their own data, never school-wide figures.
     */
    protected const SELF_ONLY_ROLES = ['parent', 'student'];

    /**
     * Platform / sub-national roles allowed to read cross-school roll-ups.
     */
    protected const GLOBAL_ROLES = [
        'owner', 'super_admin', 'ministry_admin', 'minister',
        'governorate_admin', 'directorate_admin',
    ];

    public function __construct(protected TenantManager $tenants) {}

    /**
     * Build OpenAI tool schemas for the given tool names, filtered by what the
     * user is allowed to call.
     *
     * @param  list<string>  $names
     * @return list<array<string, mixed>>
     */
    public function schemas(array $names, User $user): array
    {
        $schemas = [];

        foreach ($names as $name) {
            $definition = $this->definition($name);

            if ($definition === null || ! $this->allows($name, $user)) {
                continue;
            }

            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $definition['description'],
                    'parameters' => $definition['parameters'],
                ],
            ];
        }

        return $schemas;
    }

    /**
     * Execute a tool by name.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(string $name, array $arguments, User $user): array
    {
        $definition = $this->definition($name);

        if ($definition === null) {
            return ['error' => "أداة غير معروفة: {$name}"];
        }

        if (! $this->allows($name, $user)) {
            return ['error' => 'لا تملك صلاحية استخدام هذه الأداة.'];
        }

        try {
            return ($definition['handler'])($arguments, $user);
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'تعذّر تنفيذ الأداة: '.$e->getMessage()];
        }
    }

    public function allows(string $name, User $user): bool
    {
        $definition = $this->definition($name);

        if ($definition === null) {
            return false;
        }

        // A parent/student may only ever reach their own-data tool, no matter
        // which permission their role happens to carry.
        if ($user->hasAnyRole(self::SELF_ONLY_ROLES)) {
            return $name === 'my_children';
        }

        // Cross-school roll-ups (national_overview) are reserved for ministry,
        // governorate and directorate accounts.
        if (($definition['global'] ?? false) === true) {
            return $user->is_platform_admin || $user->hasAnyRole(self::GLOBAL_ROLES);
        }

        $permission = $definition['permission'] ?? null;

        return $permission === null || $user->can($permission) || $user->is_platform_admin;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function definition(string $name): ?array
    {
        $definitions = [
            'school_overview' => [
                'description' => 'نظرة عامة على المدرسة الحالية: عدد الطلاب، المعلمين، الفصول، الحضور اليوم، والمالية.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
                'permission' => 'reports.view',
                'handler' => fn (array $a, User $u) => $this->schoolOverview(),
            ],
            'student_count' => [
                'description' => 'عدد الطلاب، مع إمكانية التقسيم حسب الحالة أو الصف.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'group_by' => ['type' => 'string', 'enum' => ['status', 'class'], 'description' => 'طريقة التقسيم'],
                    ],
                ],
                'permission' => 'students.view',
                'handler' => fn (array $a, User $u) => $this->studentCount($a['group_by'] ?? null),
            ],
            'list_students' => [
                'description' => 'قائمة بأسماء الطلاب مع إمكانية التصفية حسب الحالة، مع حدّ أقصى للنتائج.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'حالة الطالب مثل enrolled'],
                        'limit' => ['type' => 'integer', 'description' => 'عدد النتائج (1-50)'],
                    ],
                ],
                'permission' => 'students.view',
                'handler' => fn (array $a, User $u) => $this->listStudents($a),
            ],
            'attendance_summary' => [
                'description' => 'ملخّص الحضور خلال فترة (افتراضياً آخر 30 يوماً): حاضر/غائب/متأخر.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => ['type' => 'string', 'description' => 'تاريخ البداية YYYY-MM-DD'],
                        'to' => ['type' => 'string', 'description' => 'تاريخ النهاية YYYY-MM-DD'],
                    ],
                ],
                'permission' => 'attendance.view',
                'handler' => fn (array $a, User $u) => $this->attendanceSummary($a),
            ],
            'finance_summary' => [
                'description' => 'ملخّص مالي: إجمالي الفواتير، المُحصّل، المتبقي، وعدد الفواتير المتأخرة.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
                'permission' => 'reports.view',
                'handler' => fn (array $a, User $u) => $this->financeSummary(),
            ],
            'list_unpaid_invoices' => [
                'description' => 'الفواتير غير المسدّدة (لها رصيد متبقٍ) مع اسم الطالب والمبلغ المتبقي.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'description' => 'عدد النتائج (1-50)']],
                ],
                'permission' => 'invoices.view',
                'handler' => fn (array $a, User $u) => $this->unpaidInvoices($a),
            ],
            'top_students' => [
                'description' => 'أعلى الطلاب نقاطاً في الترتيب خلال أحدث فترة تنافس.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'description' => 'عدد النتائج (1-50)']],
                ],
                'permission' => 'reports.view',
                'handler' => fn (array $a, User $u) => $this->topStudents($a),
            ],
            'my_assignments' => [
                'description' => 'نصاب المعلم الحالي: المواد والشعب التي يدرّسها.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
                'permission' => 'teachers.my-assignments',
                'handler' => fn (array $a, User $u) => $this->myAssignments($u),
            ],
            'my_children' => [
                'description' => 'بيانات أبناء المستخدم (أو الطالب نفسه): الحضور، النتائج، والرصيد المتبقي.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
                'permission' => null,
                'handler' => fn (array $a, User $u) => $this->myChildren($u),
            ],
            'national_overview' => [
                'description' => 'مقارنة على مستوى المنصة بين المدارس/المديريات (لمستوى الوزارة فقط).',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
                'permission' => 'platform.monitor',
                'global' => true,
                'handler' => fn (array $a, User $u) => $this->nationalOverview(),
            ],
        ];

        return $definitions[$name] ?? null;
    }

    // ---------------------------------------------------------------------
    // Handlers (all read-only; rely on tenant scopes for isolation).
    // ---------------------------------------------------------------------

    /** @return array<string, mixed> */
    protected function schoolOverview(): array
    {
        $today = now()->toDateString();

        $present = Attendance::query()->whereDate('attendance_date', $today)->where('status', Attendance::STATUS_PRESENT)->count();
        $absent = Attendance::query()->whereDate('attendance_date', $today)->where('status', Attendance::STATUS_ABSENT)->count();

        return [
            'date' => $today,
            'students' => Student::query()->count(),
            'students_enrolled' => Student::query()->where('status', Student::STATUS_ENROLLED)->count(),
            'teachers' => DB::table('teachers')->where('tenant_id', $this->tenants->tenantId())->count(),
            'classes' => DB::table('school_classes')->where('tenant_id', $this->tenants->tenantId())->count(),
            'attendance_today' => ['present' => $present, 'absent' => $absent],
            'finance' => $this->financeSummary(),
        ];
    }

    /** @return array<string, mixed> */
    protected function studentCount(?string $groupBy): array
    {
        $total = Student::query()->count();

        if ($groupBy === 'status') {
            return [
                'total' => $total,
                'by_status' => Student::query()
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->all(),
            ];
        }

        if ($groupBy === 'class') {
            return [
                'total' => $total,
                'by_class' => DB::table('students')
                    ->leftJoin('class_sections', 'students.class_section_id', '=', 'class_sections.id')
                    ->leftJoin('school_classes', 'class_sections.school_class_id', '=', 'school_classes.id')
                    ->where('students.tenant_id', $this->tenants->tenantId())
                    ->whereNull('students.deleted_at')
                    ->selectRaw('COALESCE(school_classes.name, \'بدون فصل\') as class_name, COUNT(*) as count')
                    ->groupBy('class_name')
                    ->pluck('count', 'class_name')
                    ->all(),
            ];
        }

        return ['total' => $total];
    }

    /** @param array<string, mixed> $a @return array<string, mixed> */
    protected function listStudents(array $a): array
    {
        $limit = max(1, min(50, (int) ($a['limit'] ?? 20)));

        $query = Student::query()->orderBy('full_name')->limit($limit);

        if (! empty($a['status'])) {
            $query->where('status', $a['status']);
        }

        return [
            'count' => $query->count(),
            'students' => $query->get(['id', 'full_name', 'student_number', 'status'])->toArray(),
        ];
    }

    /** @param array<string, mixed> $a @return array<string, mixed> */
    protected function attendanceSummary(array $a): array
    {
        $from = $a['from'] ?? now()->subDays(30)->toDateString();
        $to = $a['to'] ?? now()->toDateString();

        $rows = Attendance::query()
            ->join('attendance_sessions', 'attendances.attendance_session_id', '=', 'attendance_sessions.id')
            ->whereDate('attendance_sessions.attendance_date', '>=', $from)
            ->whereDate('attendance_sessions.attendance_date', '<=', $to)
            ->selectRaw('attendances.status as status, COUNT(*) as count')
            ->groupBy('attendances.status')
            ->pluck('count', 'status')
            ->all();

        $total = array_sum($rows);
        $present = (int) ($rows[Attendance::STATUS_PRESENT] ?? 0);

        return [
            'from' => $from,
            'to' => $to,
            'total_records' => $total,
            'by_status' => $rows,
            'attendance_rate' => $total > 0 ? round($present / $total * 100, 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function financeSummary(): array
    {
        $invoices = Invoice::query();

        return [
            'invoices_count' => (clone $invoices)->count(),
            'invoiced_total' => round((float) (clone $invoices)->sum('net_amount'), 2),
            'collected_total' => round((float) (clone $invoices)->sum('paid_amount'), 2),
            'outstanding_total' => round((float) (clone $invoices)->sum('balance'), 2),
            'unpaid_invoices' => (clone $invoices)->where('balance', '>', 0)->count(),
            'currency' => 'YER',
        ];
    }

    /** @param array<string, mixed> $a @return array<string, mixed> */
    protected function unpaidInvoices(array $a): array
    {
        $limit = max(1, min(50, (int) ($a['limit'] ?? 20)));

        return [
            'invoices' => Invoice::query()
                ->with('student:id,full_name')
                ->where('balance', '>', 0)
                ->orderByDesc('balance')
                ->limit($limit)
                ->get(['id', 'student_id', 'number', 'net_amount', 'paid_amount', 'balance', 'due_on'])
                ->toArray(),
        ];
    }

    /** @param array<string, mixed> $a @return array<string, mixed> */
    protected function topStudents(array $a): array
    {
        $limit = max(1, min(50, (int) ($a['limit'] ?? 10)));

        $entries = LeaderboardEntry::query()
            ->where('scope_type', LeaderboardEntry::SCOPE_STUDENT)
            ->orderByDesc('computed_at')
            ->orderBy('rank')
            ->limit($limit)
            ->get(['name', 'total_points', 'rank', 'computed_at'])
            ->toArray();

        return ['students' => $entries];
    }

    /** @return array<string, mixed> */
    protected function myAssignments(User $user): array
    {
        $teacherId = DB::table('teachers')->where('user_id', $user->getKey())->value('id');

        if ($teacherId === null) {
            return ['teacher' => null, 'assignments' => []];
        }

        $assignments = DB::table('teaching_assignments')
            ->leftJoin('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->leftJoin('class_sections', 'teaching_assignments.class_section_id', '=', 'class_sections.id')
            ->where('teaching_assignments.tenant_id', $this->tenants->tenantId())
            ->where('teaching_assignments.teacher_id', $teacherId)
            ->select('subjects.name as subject', 'class_sections.name as section')
            ->get()
            ->toArray();

        return ['teacher_id' => $teacherId, 'assignments' => $assignments];
    }

    /** @return array<string, mixed> */
    protected function myChildren(User $user): array
    {
        // A student sees their own record; a guardian sees their linked children.
        $students = Student::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->getKey())
                    ->orWhereHas('guardians', fn ($g) => $g->where('guardians.user_id', $user->getKey()));
            })
            ->get(['id', 'full_name', 'student_number', 'status', 'class_section_id']);

        $result = [];

        foreach ($students as $student) {
            $present = Attendance::query()->where('student_id', $student->id)
                ->where('status', Attendance::STATUS_PRESENT)->count();
            $absent = Attendance::query()->where('student_id', $student->id)
                ->where('status', Attendance::STATUS_ABSENT)->count();

            $result[] = [
                'name' => $student->full_name,
                'student_number' => $student->student_number,
                'status' => $student->status,
                'attendance' => ['present' => $present, 'absent' => $absent],
                'outstanding_balance' => round((float) Invoice::query()
                    ->where('student_id', $student->id)->sum('balance'), 2),
                'average_grade' => round((float) Grade::query()
                    ->where('student_id', $student->id)->avg('score'), 1),
            ];
        }

        return ['children' => $result];
    }

    /** @return array<string, mixed> */
    protected function nationalOverview(): array
    {
        $schools = DB::table('organizations')
            ->where('type', Organization::TYPE_SCHOOL)
            ->whereNull('deleted_at')
            ->count();

        $students = DB::table('students')->whereNull('deleted_at')->count();

        $top = LeaderboardEntry::allTenants()
            ->whereIn('scope_type', [LeaderboardEntry::SCOPE_SCHOOL, LeaderboardEntry::SCOPE_DIRECTORATE])
            ->orderByDesc('computed_at')
            ->orderBy('rank')
            ->limit(10)
            ->get(['name', 'scope_type', 'total_points', 'rank'])
            ->toArray();

        return [
            'schools' => $schools,
            'students' => $students,
            'top_entities' => $top,
        ];
    }
}
