<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Notifications\AlertDispatcher;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Scans every school for students over the absence threshold and notifies
 * their guardians. Intended to run nightly from the scheduler.
 */
class DispatchAbsenceAlerts extends Command
{
    protected $signature = 'notifications:absence-alerts
        {--from= : Start date (defaults to the start of this month)}
        {--to= : End date (defaults to today)}
        {--threshold=3 : Absences before an alert is sent}
        {--tenant= : Restrict to a single school id}';

    protected $description = 'Send absence alerts to guardians of students over the threshold';

    public function handle(AlertDispatcher $dispatcher, TenantManager $tenants): int
    {
        $from = $this->option('from') ?: now()->startOfMonth()->toDateString();
        $to = $this->option('to') ?: now()->toDateString();
        $threshold = (int) $this->option('threshold');

        $schools = Organization::query()
            ->where('type', Organization::TYPE_SCHOOL)
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $total = 0;

        foreach ($schools as $school) {
            $tenants->setTenant($school);
            $count = $dispatcher->absenceAlerts($from, $to, $threshold);

            $total += $count;
            $this->line(sprintf('%s: %d students alerted', $school->name, $count));
        }

        $tenants->setTenant(null);

        $this->info(sprintf('Done. %d students alerted across %d schools.', $total, $schools->count()));

        return self::SUCCESS;
    }
}
