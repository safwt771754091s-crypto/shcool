<?php

namespace App\Support\Permission;

use App\Models\Organization;

/**
 * Catalogue of built-in roles mapped to the administrative hierarchy.
 *
 * Levels follow Organization::LEVELS:
 *   1 ministry, 2 governorate, 3 directorate, 4 school, 5 branch.
 *
 * Global roles (ministry/governorate/directorate) have a null tenant_id and
 * apply across the whole platform. School and branch roles are tenant-scoped:
 * they are materialised per school so each school manages its own copy.
 */
final class Roles
{
    // Global roles (level 1-3).
    /** The platform owner. Distinct from an administrator: owns the product. */
    public const OWNER = 'owner';

    public const SUPER_ADMIN = 'super_admin';

    public const MINISTRY_ADMIN = 'ministry_admin';

    public const GOVERNORATE_ADMIN = 'governorate_admin';

    public const DIRECTORATE_ADMIN = 'directorate_admin';

    /**
     * Minister of Education (وزير التربية): a read-only, platform-wide
     * monitoring account. Sees the whole organisation tree and every roll-up
     * report, but can never create, edit or delete anything.
     */
    public const MINISTER = 'minister';

    // Tenant-scoped roles (level 4-5).
    public const SCHOOL_MANAGER = 'school_manager';

    public const BRANCH_MANAGER = 'branch_manager';

    public const VICE_PRINCIPAL = 'vice_principal';

    public const TEACHER = 'teacher';

    public const STUDENT_AFFAIRS = 'student_affairs';

    public const ACCOUNTANT = 'accountant';

    public const SECRETARY = 'secretary';

    public const TEACHER_ASSISTANT = 'teacher_assistant';

    public const PARENT = 'parent';

    public const STUDENT = 'student';

    /**
     * @return list<RoleDefinition>
     */
    public static function definitions(): array
    {
        return [
            new RoleDefinition(self::OWNER, 'مالك المنصة', 1, ['*'], global: true),
            new RoleDefinition(self::SUPER_ADMIN, 'مدير المنصة', 1, [
                'platform.view', 'platform.manage', 'platform.manage-admins', 'platform.impersonate',
                'organizations.*', 'users.*', 'roles.*', 'reports.*', 'audit.view', 'settings.*',
                'notifications.*', 'apps.*', 'sports.*', 'activities.*',
                'students.*', 'teachers.*', 'attendance.*', 'exams.*', 'classes.*',
            ], global: true),
            new RoleDefinition(self::MINISTRY_ADMIN, 'مدير الوزارة', 1, [
                'organizations.*', 'users.*', 'roles.view', 'roles.assign',
                'reports.view', 'reports.export', 'audit.view', 'settings.*',
                'notifications.*', 'students.view', 'teachers.view',
                'apps.view', 'apps.publish', 'sports.*', 'activities.*',
                'curriculum.view', 'teaching.view', 'reports.view',
            ], global: true),
            // Read-only monitoring: the whole tree plus every roll-up report,
            // but no create/update/delete permissions anywhere.
            new RoleDefinition(self::MINISTER, 'وزير التربية', 1, [
                'platform.monitor',
                'organizations.view', 'users.view', 'roles.view', 'audit.view',
                'reports.view', 'reports.export', 'settings.view',
                'students.view', 'teachers.view', 'classes.view',
                'attendance.view', 'attendance.report',
                'exams.view', 'fees.view', 'invoices.view', 'payments.view',
                'parents.view', 'curriculum.view', 'teaching.view',
                'apps.view', 'sports.view', 'activities.view',
                'notifications.view',
            ], global: true),
            new RoleDefinition(self::GOVERNORATE_ADMIN, 'مدير المحافظة', 2, [
                'organizations.view', 'organizations.create', 'organizations.update',
                'users.view', 'users.create', 'users.update',
                'roles.view', 'roles.assign', 'reports.view', 'reports.export',
                'students.view', 'teachers.view', 'attendance.report',
                'exams.view', 'fees.view',
                'apps.view', 'apps.publish', 'sports.view', 'sports.schedule',
                'activities.view',
            ], global: true),
            new RoleDefinition(self::DIRECTORATE_ADMIN, 'مدير المديرية', 3, [
                'organizations.view', 'organizations.update',
                'users.view', 'users.create', 'users.update',
                'roles.view', 'roles.assign', 'reports.view', 'reports.export',
                'students.*', 'teachers.*', 'attendance.*', 'exams.*',
                'fees.view', 'classes.view',
                'apps.view', 'apps.publish', 'sports.*', 'activities.*',
                'curriculum.view', 'teaching.view', 'teaching.review',
            ], global: true),

            new RoleDefinition(self::SCHOOL_MANAGER, 'مدير المدرسة', 4, ['*']),
            new RoleDefinition(self::BRANCH_MANAGER, 'مدير الفرع', 5, [
                'organizations.view', 'users.view', 'users.create', 'users.update',
                'students.*', 'teachers.*', 'classes.*', 'subjects.view',
                'sessions.view', 'attendance.*', 'exams.*',
                'fees.*', 'invoices.*', 'payments.*',
                'reports.view', 'reports.export', 'notifications.view', 'notifications.send',
                'curriculum.*', 'teaching.*', 'assignments.*',
                'sports.*', 'activities.*', 'apps.view', 'apps.publish',
            ]),
            new RoleDefinition(self::VICE_PRINCIPAL, 'وكيل المدرسة', 4, [
                'students.*', 'teachers.view', 'classes.*', 'subjects.view',
                'sessions.view', 'attendance.*', 'exams.*', 'reports.view',
                'notifications.view', 'notifications.send', 'parents.view',
                'curriculum.*', 'teaching.view', 'teaching.review', 'teaching.approve',
                'assignments.view', 'sports.view', 'sports.manage', 'activities.*',
            ]),
            new RoleDefinition(self::TEACHER, 'معلم', 4, [
                'students.view', 'students.view-profile', 'classes.view',
                'subjects.view', 'attendance.view', 'attendance.create', 'attendance.update',
                'exams.view', 'exams.grades.enter', 'reports.view', 'teachers.my-assignments',
                'curriculum.view', 'teaching.view', 'teaching.prepare', 'teaching.submit',
                'assignments.view', 'assignments.create', 'assignments.update', 'assignments.grade',
                'sports.view', 'activities.view', 'activities.manage',
            ]),
            new RoleDefinition(self::TEACHER_ASSISTANT, 'معلم مساعد', 4, [
                'students.view', 'classes.view', 'subjects.view',
                'attendance.view', 'exams.view', 'exams.grades.enter',
                'curriculum.view', 'teaching.view', 'assignments.view', 'activities.view',
                'teachers.my-assignments',
            ]),
            new RoleDefinition(self::STUDENT_AFFAIRS, 'شؤون الطلاب', 4, [
                'students.*', 'classes.view', 'attendance.*', 'reports.view',
                'parents.view', 'parents.create', 'parents.update', 'parents.link-students',
            ]),
            new RoleDefinition(self::ACCOUNTANT, 'محاسب', 4, [
                'fees.*', 'invoices.*', 'payments.*', 'students.view', 'reports.view', 'reports.export',
            ]),
            new RoleDefinition(self::SECRETARY, 'سكرتير', 4, [
                'students.view', 'students.create', 'students.update', 'teachers.view',
                'parents.view', 'parents.create', 'notifications.view', 'notifications.send',
            ]),
            new RoleDefinition(self::PARENT, 'ولي أمر', 4, [
                'students.view-profile', 'exams.view', 'attendance.view',
                'fees.view', 'invoices.view', 'payments.view', 'reports.view',
                'teaching.view', 'assignments.view', 'activities.view', 'sports.view',
            ]),
            new RoleDefinition(self::STUDENT, 'طالب', 4, [
                'exams.view', 'attendance.view', 'fees.view', 'invoices.view',
                'assignments.view', 'activities.view', 'activities.submit', 'sports.view',
            ]),
        ];
    }

    /**
     * Roles materialised inside every school.
     *
     * @return list<string>
     */
    public static function tenantRoleNames(): array
    {
        return array_values(array_map(
            fn (RoleDefinition $d) => $d->name,
            array_filter(self::definitions(), fn (RoleDefinition $d) => ! $d->global),
        ));
    }

    /**
     * @return list<string>
     */
    public static function globalRoleNames(): array
    {
        return array_values(array_map(
            fn (RoleDefinition $d) => $d->name,
            array_filter(self::definitions(), fn (RoleDefinition $d) => $d->global),
        ));
    }
}
