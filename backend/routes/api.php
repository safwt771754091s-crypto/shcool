<?php

use App\Http\Controllers\Api\AcademicController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MiniAppController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SportsLeagueController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TeacherDashboardController;
use App\Http\Controllers\Api\TeachingController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — School Digital Platform (المرحلة الأولى)
|--------------------------------------------------------------------------
| All routes are prefixed with /api and pass through InitializeTenancy.
*/

Route::prefix('v1')->group(function (): void {
    // ---- Public -------------------------------------------------------
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // ---- Authenticated ------------------------------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Two-factor challenge (uses the restricted 2fa-challenge token).
        Route::post('auth/2fa/challenge', [AuthController::class, 'challenge'])
            ->middleware('throttle:6,1');

        // Everything below requires a fully verified session.
        Route::middleware('2fa')->group(function (): void {
            // 2FA management
            Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
            Route::post('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);

            // Administrative hierarchy
            Route::get('organizations/tree', [OrganizationController::class, 'tree'])
                ->middleware('permission:organizations.view');
            Route::get('organizations', [OrganizationController::class, 'index'])
                ->middleware('permission:organizations.view');
            Route::post('organizations', [OrganizationController::class, 'store'])
                ->middleware('permission:organizations.create');
            Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
                ->middleware('permission:organizations.view');
            Route::put('organizations/{organization}', [OrganizationController::class, 'update'])
                ->middleware('permission:organizations.update');

            // Competition / leaderboards
            Route::get('ranking-periods', [LeaderboardController::class, 'periods'])
                ->middleware('permission:reports.view');
            Route::post('ranking-periods', [LeaderboardController::class, 'storePeriod'])
                ->middleware('permission:reports.export');
            Route::get('ranking-periods/{period}/leaderboard', [LeaderboardController::class, 'index'])
                ->middleware('permission:reports.view');
            Route::get('ranking-periods/{period}/my-position', [LeaderboardController::class, 'myPosition'])
                ->middleware('permission:reports.view');
            Route::get('ranking-periods/{period}/classes', [LeaderboardController::class, 'classLeaderboard'])
                ->middleware('permission:reports.view');
            Route::get('ranking-periods/{period}/my-student-position', [LeaderboardController::class, 'myStudentPosition']);
            Route::post('ranking-periods/{period}/recompute', [LeaderboardController::class, 'recompute'])
                ->middleware('permission:reports.export');
            Route::post('ranking-periods/{period}/recompute-academics', [LeaderboardController::class, 'recomputeFromAcademics'])
                ->middleware('permission:reports.export');

            // ---- Academic structure -----------------------------------
            Route::get('academic/years', [AcademicController::class, 'years'])
                ->middleware('permission:classes.view');
            Route::post('academic/years', [AcademicController::class, 'storeYear'])
                ->middleware('permission:sessions.create');
            Route::post('academic/years/{year}/terms', [AcademicController::class, 'storeTerm'])
                ->middleware('permission:sessions.create');

            Route::get('academic/subjects', [AcademicController::class, 'subjects'])
                ->middleware('permission:subjects.view');
            Route::post('academic/subjects', [AcademicController::class, 'storeSubject'])
                ->middleware('permission:subjects.create');

            Route::get('academic/classes', [AcademicController::class, 'classes'])
                ->middleware('permission:classes.view');
            Route::post('academic/classes', [AcademicController::class, 'storeClass'])
                ->middleware('permission:classes.create');
            Route::post('academic/classes/{class}/sections', [AcademicController::class, 'storeSection'])
                ->middleware('permission:classes.create');

            Route::get('academic/units', [AcademicController::class, 'units'])
                ->middleware('permission:curriculum.view');
            Route::post('academic/units', [AcademicController::class, 'storeUnit'])
                ->middleware('permission:curriculum.manage');
            Route::post('academic/units/{unit}/lessons', [AcademicController::class, 'storeLesson'])
                ->middleware('permission:curriculum.manage');

            // ---- Teaching / preparation notebook ----------------------
            Route::get('teaching/preparations', [TeachingController::class, 'index'])
                ->middleware('permission:teaching.view');
            Route::post('teaching/preparations', [TeachingController::class, 'store'])
                ->middleware('permission:teaching.prepare');
            Route::put('teaching/preparations/{preparation}', [TeachingController::class, 'update'])
                ->middleware('permission:teaching.prepare');
            Route::post('teaching/preparations/{preparation}/submit', [TeachingController::class, 'submit'])
                ->middleware('permission:teaching.submit');
            Route::post('teaching/preparations/{preparation}/review', [TeachingController::class, 'review'])
                ->middleware('permission:teaching.review');

            // ---- Sports league ----------------------------------------
            Route::get('sports/competitions', [SportsLeagueController::class, 'index'])
                ->middleware('permission:sports.view');
            Route::post('sports/competitions', [SportsLeagueController::class, 'store'])
                ->middleware('permission:sports.manage');
            Route::get('sports/competitions/{competition}', [SportsLeagueController::class, 'show'])
                ->middleware('permission:sports.view');
            Route::post('sports/competitions/{competition}/participants', [SportsLeagueController::class, 'registerParticipant'])
                ->middleware('permission:sports.register');
            Route::post('sports/competitions/{competition}/bracket', [SportsLeagueController::class, 'generateBracket'])
                ->middleware('permission:sports.schedule');
            Route::post('sports/competitions/{competition}/advance', [SportsLeagueController::class, 'advance'])
                ->middleware('permission:sports.schedule');
            Route::post('sports/matches/{match}/result', [SportsLeagueController::class, 'recordResult'])
                ->middleware('permission:sports.record-results');

            // ---- Interactive activities -------------------------------
            Route::get('activities', [ActivityController::class, 'index'])
                ->middleware('permission:activities.view');
            Route::post('activities', [ActivityController::class, 'store'])
                ->middleware('permission:activities.manage');
            Route::get('activities/my-results', [ActivityController::class, 'myResults'])
                ->middleware('permission:activities.view');
            Route::get('activities/{activity}', [ActivityController::class, 'show'])
                ->middleware('permission:activities.view');
            Route::post('activities/{activity}/submit', [ActivityController::class, 'submit'])
                ->middleware('permission:activities.submit');

            // ---- Platform monitoring (read-only) ------------------------
            // The whole-country view for the Minister of Education
            // (وزير التربية) and the platform owner. Strictly read-only.
            Route::prefix('monitoring')->group(function (): void {
                Route::get('overview', [MonitoringController::class, 'overview'])
                    ->middleware('permission:platform.monitor');
                Route::get('tree', [MonitoringController::class, 'tree'])
                    ->middleware('permission:platform.monitor');
                Route::get('schools', [MonitoringController::class, 'schools'])
                    ->middleware('permission:platform.monitor');
            });

            // ---- Staff accounts & role assignment ---------------------
            // Create a manager/staff account inside an organization and grant
            // a tenant-scoped role (school_manager, teacher, accountant, ...).
            Route::get('users', [UserManagementController::class, 'index'])
                ->middleware('permission:users.view');
            Route::post('users', [UserManagementController::class, 'store'])
                ->middleware('permission:users.create');
            Route::post('users/{user}/roles', [UserManagementController::class, 'assignRoleToUser'])
                ->middleware('permission:roles.assign');

            // ---- Mini-app registry ------------------------------------
            Route::get('apps', [MiniAppController::class, 'index'])
                ->middleware('permission:apps.view');
            Route::post('apps', [MiniAppController::class, 'store'])
                ->middleware('permission:apps.manage');
            Route::get('apps/available', [MiniAppController::class, 'available'])
                ->middleware('permission:apps.view');
            Route::post('apps/{app}/publish', [MiniAppController::class, 'publish'])
                ->middleware('permission:apps.publish');

            // ---- Students & admissions --------------------------------
            Route::get('students', [StudentController::class, 'index'])
                ->middleware('permission:students.view');
            Route::post('students', [StudentController::class, 'store'])
                ->middleware('permission:students.create');
            Route::get('students/my-children', [StudentController::class, 'myChildren'])
                ->middleware('permission:students.view-profile');
            Route::get('students/{student}', [StudentController::class, 'show'])
                ->middleware('permission:students.view');
            Route::put('students/{student}', [StudentController::class, 'update'])
                ->middleware('permission:students.update');
            Route::post('students/{student}/transfer', [StudentController::class, 'transfer'])
                ->middleware('permission:students.promote');
            Route::post('students/{student}/status', [StudentController::class, 'changeStatus'])
                ->middleware('permission:students.update');

            Route::get('admissions', [StudentController::class, 'admissions'])
                ->middleware('permission:students.view');
            Route::post('admissions', [StudentController::class, 'storeAdmission'])
                ->middleware('permission:students.create');
            Route::post('admissions/{admission}/decide', [StudentController::class, 'decideAdmission'])
                ->middleware('permission:students.update');
            Route::post('admissions/{admission}/enrol', [StudentController::class, 'enrolAdmission'])
                ->middleware('permission:students.create');

            // ---- Guardians / parents ----------------------------------
            Route::get('guardians', [GuardianController::class, 'index'])
                ->middleware('permission:parents.view');
            Route::post('guardians', [GuardianController::class, 'store'])
                ->middleware('permission:parents.create');
            Route::get('guardians/{guardian}', [GuardianController::class, 'show'])
                ->middleware('permission:parents.view');
            Route::put('guardians/{guardian}', [GuardianController::class, 'update'])
                ->middleware('permission:parents.update');
            Route::post('guardians/{guardian}/link', [GuardianController::class, 'link'])
                ->middleware('permission:parents.link-students');

            // ---- Teachers & assignments -------------------------------
            Route::get('teachers', [TeacherController::class, 'index'])
                ->middleware('permission:teachers.view');
            Route::post('teachers', [TeacherController::class, 'store'])
                ->middleware('permission:teachers.create');
            Route::get('teachers/my-assignments', [TeacherController::class, 'myAssignments'])
                ->middleware('permission:teachers.my-assignments');
            Route::get('teachers/dashboard', [TeacherDashboardController::class, 'show'])
                ->middleware('permission:teachers.my-assignments');
            Route::get('teachers/{teacher}', [TeacherController::class, 'show'])
                ->middleware('permission:teachers.view');
            Route::put('teachers/{teacher}', [TeacherController::class, 'update'])
                ->middleware('permission:teachers.update');
            Route::post('teachers/{teacher}/assign', [TeacherController::class, 'assign'])
                ->middleware('permission:teachers.assign');

            // ---- Attendance -------------------------------------------
            Route::post('attendance/register', [AttendanceController::class, 'takeRegister'])
                ->middleware('permission:attendance.create');
            Route::get('attendance/session', [AttendanceController::class, 'session'])
                ->middleware('permission:attendance.view');
            Route::get('attendance/report', [AttendanceController::class, 'report'])
                ->middleware('permission:attendance.report');
            Route::get('attendance/absence-alerts', [AttendanceController::class, 'absenceAlerts'])
                ->middleware('permission:attendance.report');

            // ---- Exams & grades ---------------------------------------
            Route::get('exams', [ExamController::class, 'index'])
                ->middleware('permission:exams.view');
            Route::post('exams', [ExamController::class, 'store'])
                ->middleware('permission:exams.create');
            Route::get('exams/my-result', [ExamController::class, 'myResultSheet'])
                ->middleware('permission:exams.view');
            Route::get('exams/result-sheet', [ExamController::class, 'sectionResultSheet'])
                ->middleware('permission:exams.view');
            Route::get('exams/{exam}', [ExamController::class, 'show'])
                ->middleware('permission:exams.view');
            Route::post('exams/{exam}/grades', [ExamController::class, 'recordGrades'])
                ->middleware('permission:exams.grades.enter');
            Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])
                ->middleware('permission:exams.grades.publish');
            Route::get('students/{student}/result-sheet', [ExamController::class, 'resultSheet'])
                ->middleware('permission:exams.view');

            // ---- Offline sync -----------------------------------------
            Route::post('sync/push', [SyncController::class, 'push'])
                ->middleware('permission:attendance.create');

            // ---- Reports & exports ------------------------------------
            Route::get('reports', [ReportController::class, 'index'])
                ->middleware('permission:reports.view');
            Route::get('reports/schools-overview', [ReportController::class, 'schoolsOverview'])
                ->middleware('permission:reports.view');
            Route::get('reports/export/{format}', [ReportController::class, 'export'])
                ->whereIn('format', ['pdf', 'xlsx', 'csv'])
                ->middleware('permission:reports.export');

            // ---- Notifications ----------------------------------------
            Route::get('notifications/channels', [NotificationController::class, 'channels'])
                ->middleware('permission:notifications.view');
            Route::get('notifications/templates', [NotificationController::class, 'templates'])
                ->middleware('permission:notifications.view');
            Route::post('notifications/templates', [NotificationController::class, 'storeTemplate'])
                ->middleware('permission:notifications.manage-templates');
            Route::post('notifications/send', [NotificationController::class, 'send'])
                ->middleware('permission:notifications.send');
            Route::get('notifications/logs', [NotificationController::class, 'logs'])
                ->middleware('permission:notifications.view');
            Route::get('notifications/inbox', [NotificationController::class, 'inbox']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
            Route::get('notifications/preferences', [NotificationController::class, 'preferences']);
            Route::put('notifications/preferences', [NotificationController::class, 'updatePreferences']);

            // ---- Finance: fees, invoices, payments --------------------
            Route::get('fees', [FinanceController::class, 'fees'])
                ->middleware('permission:fees.view');
            Route::post('fees', [FinanceController::class, 'storeFee'])
                ->middleware('permission:fees.create');
            Route::get('invoices', [FinanceController::class, 'invoices'])
                ->middleware('permission:invoices.view');
            Route::post('invoices', [FinanceController::class, 'storeInvoice'])
                ->middleware('permission:invoices.create');
            Route::post('invoices/issue-term', [FinanceController::class, 'issueTerm'])
                ->middleware('permission:invoices.create');
            Route::get('invoices/{invoice}', [FinanceController::class, 'showInvoice'])
                ->middleware('permission:invoices.view');
            Route::post('invoices/{invoice}/cancel', [FinanceController::class, 'cancelInvoice'])
                ->middleware('permission:invoices.update');
            Route::get('payments', [FinanceController::class, 'payments'])
                ->middleware('permission:payments.view');
            Route::post('payments', [FinanceController::class, 'storePayment'])
                ->middleware('permission:fees.collect');
            Route::get('finance/summary', [FinanceController::class, 'summary'])
                ->middleware('permission:reports.view');
            Route::get('students/{student}/balance', [FinanceController::class, 'studentBalance'])
                ->middleware('permission:fees.view');

            // ---- Bulk import (Excel/CSV templates) --------------------
            Route::get('imports/templates', [ImportController::class, 'templates'])
                ->middleware('permission:students.view');
            Route::get('imports/templates/{type}', [ImportController::class, 'template'])
                ->middleware('permission:students.view');
            Route::post('imports', [ImportController::class, 'store'])
                ->middleware('permission:students.create');

            // ---- Parent & student portals -----------------------------
            // A guardian reaches their own dashboard through their `parent`
            // role; staff with the administrative `parents.view` permission may
            // use it too. The endpoint itself only ever returns the caller's own
            // children, so this is safe.
            Route::get('portal/parent', [PortalController::class, 'parent'])
                ->middleware('role_or_permission:parent|parents.view');
            Route::get('portal/student', [PortalController::class, 'student']);

            // ---- AI agents (وكلاء الذكاء الاصطناعي) --------------------
            // Read-only assistants. The controller scopes conversations to the
            // caller and each tool re-checks the caller's permissions, so a
            // parent/student can only ever reach their own data.
            Route::get('ai', [AiController::class, 'index'])
                ->middleware('permission:ai.view');
            Route::post('ai/conversations', [AiController::class, 'store'])
                ->middleware('permission:ai.chat');
            Route::get('ai/conversations/{conversation}', [AiController::class, 'show'])
                ->middleware('permission:ai.view');
            Route::post('ai/conversations/{conversation}/messages', [AiController::class, 'message'])
                ->middleware('permission:ai.chat');
            Route::delete('ai/conversations/{conversation}', [AiController::class, 'destroy'])
                ->middleware('permission:ai.chat');
        });
    });
});
