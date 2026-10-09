<?php

namespace App\Support\Permission;

/**
 * Central catalogue of permission names grouped by module.
 *
 * Naming convention: "<module>.<action>". Permissions are global; roles are
 * tenant-scoped. Add new entries here and they are created by the
 * PermissionSeeder.
 */
final class Permissions
{
    public const MODULES = [
        'organizations' => ['view', 'create', 'update', 'delete', 'manage-branches'],
        'users' => ['view', 'create', 'update', 'delete', 'impersonate'],
        'roles' => ['view', 'create', 'update', 'delete', 'assign'],
        'students' => ['view', 'create', 'update', 'delete', 'promote', 'view-profile'],
        'teachers' => ['view', 'create', 'update', 'delete', 'assign', 'my-assignments'],
        'classes' => ['view', 'create', 'update', 'delete'],
        'subjects' => ['view', 'create', 'update', 'delete'],
        'sessions' => ['view', 'create', 'update', 'delete'],
        'attendance' => ['view', 'create', 'update', 'delete', 'report'],
        'exams' => ['view', 'create', 'update', 'delete', 'grades.enter', 'grades.publish'],
        'fees' => ['view', 'create', 'update', 'delete', 'collect', 'refund'],
        'invoices' => ['view', 'create', 'update', 'delete', 'print'],
        'payments' => ['view', 'create', 'update', 'delete', 'reconcile'],
        'reports' => ['view', 'export'],
        'notifications' => ['view', 'send', 'manage-templates'],
        'parents' => ['view', 'create', 'update', 'link-students'],
        'settings' => ['view', 'update'],
        'audit' => ['view'],

        // Teaching lifecycle: the curriculum and the preparation notebook.
        'curriculum' => ['view', 'manage'],
        'teaching' => ['view', 'prepare', 'submit', 'review', 'approve'],
        'assignments' => ['view', 'create', 'update', 'delete', 'grade'],

        // Sports league (الدوري الرياضي) and interactive activities.
        'sports' => ['view', 'manage', 'register', 'schedule', 'record-results'],
        'activities' => ['view', 'manage', 'submit'],

        // Mini-app registry (سجل التطبيقات المصغّرة).
        'apps' => ['view', 'manage', 'publish'],

        // Platform ownership: reserved for the owner account.
        'platform' => ['view', 'manage', 'manage-admins', 'impersonate', 'monitor'],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }

    public static function moduleOf(string $permission): ?string
    {
        return explode('.', $permission, 2)[0] ?? null;
    }
}
