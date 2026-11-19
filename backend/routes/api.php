<?php

use App\Modules\Auth\AuthController;
use App\Modules\Branches\BranchController;
use App\Modules\Connector\WebhookController;
use App\Modules\Imports\ImportController;
use App\Modules\Inbox\MessageController;
use App\Modules\Integration\IntegrationController;
use App\Modules\Integration\PortalLeadController;
use App\Modules\Reports\ReportController;
use App\Modules\Inbox\SimulatorController;
use App\Modules\Leads\LeadController;
use App\Modules\Leads\LeadTypeController;
use App\Modules\Settings\SettingsController;
use App\Modules\Users\UserController;
use App\Modules\Workflow\NotificationController;
use App\Modules\Workflow\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware(['auth:sanctum', 'active']);
});

Route::post('webhooks/whatsapp/{provider}', WebhookController::class)->middleware('throttle:600,1');

Route::prefix('v1')->middleware(['auth:sanctum', 'portal', 'throttle:120,1'])->group(function () {
    Route::get('leads', [PortalLeadController::class, 'index']);
    Route::get('leads/{lead}', [PortalLeadController::class, 'show'])->whereNumber('lead');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::apiResource('users', UserController::class)->except('show');
    Route::apiResource('branches', BranchController::class)->except('show');

    Route::get('settings', [SettingsController::class, 'index']);
    Route::post('settings/provider/check', [SettingsController::class, 'checkProvider']);
    Route::get('settings/{key}', [SettingsController::class, 'show']);
    Route::put('settings/{key}', [SettingsController::class, 'update']);

    Route::get('lead-types', [LeadTypeController::class, 'index']);
    Route::post('lead-types', [LeadTypeController::class, 'store']);
    Route::put('lead-types/{leadType}', [LeadTypeController::class, 'update']);
    Route::delete('lead-types/{leadType}', [LeadTypeController::class, 'destroy']);

    Route::get('reports', [ReportController::class, 'index']);
    Route::get('reports/export', [ReportController::class, 'export']);

    Route::get('integrations', [IntegrationController::class, 'index']);
    Route::post('integrations/clients', [IntegrationController::class, 'storeClient']);
    Route::delete('integrations/clients/{client}', [IntegrationController::class, 'destroyClient']);
    Route::post('integrations/webhooks', [IntegrationController::class, 'storeWebhook']);
    Route::patch('integrations/webhooks/{webhook}', [IntegrationController::class, 'updateWebhook']);
    Route::delete('integrations/webhooks/{webhook}', [IntegrationController::class, 'destroyWebhook']);
    Route::post('integrations/webhooks/{webhook}/test', [IntegrationController::class, 'testWebhook']);

    Route::get('leads', [LeadController::class, 'index']);
    Route::get('leads/summary', [LeadController::class, 'summary']);
    Route::get('leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead');
    Route::patch('leads/{lead}', [LeadController::class, 'update'])->whereNumber('lead');
    Route::post('leads/{lead}/assign', [WorkflowController::class, 'assign'])->whereNumber('lead');
    Route::post('leads/{lead}/reply-sent', [WorkflowController::class, 'replySent'])->whereNumber('lead');
    Route::post('leads/{lead}/status', [WorkflowController::class, 'status'])->whereNumber('lead');
    Route::post('leads/{lead}/remind', [WorkflowController::class, 'remind'])->whereNumber('lead');
    Route::post('leads/{lead}/notes', [WorkflowController::class, 'storeNote'])->whereNumber('lead');
    Route::get('leads/{lead}/activity', [WorkflowController::class, 'timeline'])->whereNumber('lead');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'read']);

    Route::get('imports', [ImportController::class, 'index']);
    Route::post('imports', [ImportController::class, 'store']);
    Route::get('imports/{import}', [ImportController::class, 'show']);
    Route::post('imports/{import}/run', [ImportController::class, 'run']);
    Route::post('leads/{lead}/read', [LeadController::class, 'markRead'])->whereNumber('lead');
    Route::get('leads/{lead}/messages', [MessageController::class, 'index']);
    Route::post('leads/{lead}/messages', [MessageController::class, 'store']);
    Route::get('messages/{message}/media', [MessageController::class, 'media']);

    if (app()->environment('local')) {
        Route::prefix('dev')->middleware('can:manage-settings')->group(function () {
            Route::get('messages', [SimulatorController::class, 'messages']);
            Route::post('simulate', [SimulatorController::class, 'simulate']);
        });
    }
});
