<?php

namespace Tests\Feature;

use App\Models\Notification\NotificationLog;
use App\Models\Notification\NotificationPreference;
use App\Models\Notification\NotificationTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Services\Notifications\Channels\InAppChannel;
use App\Services\Notifications\NotificationChannelManager;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_is_rendered_with_placeholders(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        NotificationTemplate::create([
            'key' => 'attendance.absence_alert',
            'channel' => NotificationTemplate::CHANNEL_SMS,
            'body' => 'غياب :student_name (:absences)',
        ]);

        [$title, $body] = app(NotificationService::class)->render(
            'attendance.absence_alert',
            NotificationTemplate::CHANNEL_SMS,
            ['student_name' => 'علي', 'absences' => 4],
        );

        $this->assertSame('غياب علي (4)', $body);
        $this->assertNotEmpty($title);
    }

    public function test_falls_back_to_builtin_wording_without_a_template(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        [, $body] = app(NotificationService::class)->render(
            'exams.result_published',
            NotificationTemplate::CHANNEL_SMS,
            ['student_name' => 'سارة', 'average' => 88.5],
        );

        $this->assertStringContainsString('سارة', $body);
        $this->assertStringContainsString('88.5', $body);
    }

    public function test_in_app_notification_is_delivered_and_logged(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $user = User::factory()->create(['tenant_id' => $school->id]);

        $logs = app(NotificationService::class)->sendToUser(
            $user,
            'auth.welcome',
            ['name' => 'أحمد'],
            [NotificationTemplate::CHANNEL_IN_APP],
        );

        $this->assertCount(1, $logs);
        $this->assertSame(NotificationLog::STATUS_SENT, $logs[0]->refresh()->status);
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_channel_preferences_block_delivery(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $user = User::factory()->create(['tenant_id' => $school->id]);

        NotificationPreference::create([
            'user_id' => $user->id,
            'channel' => NotificationTemplate::CHANNEL_IN_APP,
            'is_enabled' => false,
        ]);

        $logs = app(NotificationService::class)->sendToUser(
            $user,
            'auth.welcome',
            ['name' => 'أحمد'],
            [NotificationTemplate::CHANNEL_IN_APP],
        );

        $this->assertSame([], $logs);
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_unconfigured_sms_channel_is_not_available(): void
    {
        $manager = app(NotificationChannelManager::class);

        $this->assertTrue($manager->has('sms'));
        $this->assertContains('in_app', $manager->available());
        $this->assertNotContains('sms', $manager->available());
    }

    public function test_failed_channel_marks_the_log_failed(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        // SMS has no credentials, so delivery must fail loudly and be recorded.
        $log = NotificationLog::create([
            'channel' => 'sms',
            'recipient' => '+967000000',
            'body' => 'test',
            'status' => NotificationLog::STATUS_QUEUED,
        ]);

        app(NotificationService::class)->deliver($log);

        $this->assertSame(NotificationLog::STATUS_FAILED, $log->refresh()->status);
        $this->assertNotNull($log->error);
    }

    public function test_absence_alert_reaches_the_guardians_phone(): void
    {
        // Configure the SMS gateway before the channel singleton is resolved.
        config()->set('services.sms', [
            'endpoint' => 'https://sms.example.test/send',
            'username' => 'user',
            'password' => 'secret',
            'sender' => 'SchoolPlatform',
        ]);

        Http::fake([
            'sms.example.test/*' => Http::response(['sid' => 'SM-123'], 200),
        ]);

        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $class = \App\Models\Academic\SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);

        $student = \App\Models\Student\Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-0009',
            'full_name' => 'علي محمد',
            'status' => \App\Models\Student\Student::STATUS_ENROLLED,
        ]);

        $guardian = \App\Models\Student\Guardian::create([
            'full_name' => 'محمد',
            'relation' => \App\Models\Student\Guardian::RELATION_FATHER,
            'phone' => '+967711111111',
        ]);

        app(\App\Services\Students\StudentService::class)
            ->linkGuardian($student, $guardian, \App\Models\Student\Guardian::RELATION_FATHER, true);

        $attendance = app(\App\Services\Attendance\AttendanceService::class);

        foreach (['2026-10-01', '2026-10-02', '2026-10-03'] as $date) {
            $attendance->takeRegister($section->getKey(), $date, [
                ['student_id' => $student->id, 'status' => \App\Models\Attendance\Attendance::STATUS_ABSENT],
            ]);
        }

        $alerted = app(\App\Services\Notifications\AlertDispatcher::class)
            ->absenceAlerts('2026-10-01', '2026-10-03', threshold: 3);

        $this->assertSame(1, $alerted);

        $log = NotificationLog::query()->where('template_key', 'attendance.absence_alert')->first();
        $this->assertNotNull($log);
        $this->assertSame('+967711111111', $log->recipient);
        $this->assertSame(NotificationLog::STATUS_SENT, $log->status);
        $this->assertSame('SM-123', $log->provider_message_id);
        $this->assertStringContainsString('علي محمد', $log->body);
    }
}
