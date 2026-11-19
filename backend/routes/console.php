<?php

use App\Modules\Workflow\LeadWorkflow;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('leads:auto-remind', function (LeadWorkflow $workflow) {
    $this->info($workflow->sendDueReminders().' automatic reminder(s) sent.');
})->purpose('Send automatic "reply not sent" reminders when enabled in settings');

Schedule::command('leads:auto-remind')->everyFiveMinutes()->withoutOverlapping();
