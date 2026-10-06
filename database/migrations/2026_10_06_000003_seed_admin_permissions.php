<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'class_add','class_view','class_update','class_delete',
            'section_add','section_view','section_update','section_delete','section_time_table',
            'subject_add','subject_view','subject_update','subject_delete',
            'paper_add','paper_view','paper_update','paper_delete',
            'student_add','student_view','student_info','student_student_portal_access',
            'student_update','student_delete','student_student_bulk_add',
            'teacher_add','teacher_view','teacher_update','teacher_delete','teacher_bulk_add',
            'teacher_portal_access','teacher_timetable_add',
            'add_student_attendance','teacher_timetable_view','view_student_attendance',
            'view_student_monthly_reports',
            'exam_add','exam_view','exam_update','exam_delete',
            'gpa_rule_add','gpa_rule_view','gpa_rule_update','gpa_rule_delete',
            'add_marks','view_marks','update_marks','delete_marks',
            'accounting','add_fess','view_fess','update_fess','delete_fess',
        ];

        foreach ($permissions as $name) {
            $exists = DB::table('permission')
                ->where('permission_group', 'admin')
                ->where('permission_name', $name)
                ->exists();

            if (!$exists) {
                DB::table('permission')->insert([
                    'permission_name' => $name,
                    'permission_group' => 'admin',
                    'permission_type' => 'yes',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permission')
                    ->where('permission_group', 'admin')
                    ->where('permission_name', $name)
                    ->update(['permission_type' => 'yes', 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Keep existing permission data intact on rollback.
    }
};