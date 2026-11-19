<?php

namespace App\Modules\Workflow;

use App\Modules\Leads\Models\ActivityLog;
use Illuminate\Support\Str;

/** Human-readable sentences for activity_log rows. */
class ActivityPresenter
{
    private const SOURCES = [
        'whatsapp_enquiry' => 'WhatsApp enquiry', 'agency_text' => 'agency text', 'agency_file' => 'agency file', 'manual_upload' => 'manual upload',
    ];

    private const STATUSES = [
        'new' => 'New', 'assigned' => 'Assigned', 'contacted' => 'Contacted', 'follow_up' => 'Follow-up',
        'converted' => 'Converted', 'lost' => 'Lost', 'closed' => 'Closed',
    ];

    private const OUTCOMES = [
        'connected' => 'connected', 'no_answer' => 'no answer', 'busy' => 'busy', 'wrong_number' => 'wrong number', 'call_back' => 'call back later',
    ];

    public static function text(ActivityLog $entry): string
    {
        $d = $entry->details ?? [];
        $who = $entry->user?->name ?? 'System';

        return match ($entry->action) {
            'created' => 'Lead created from '.(self::SOURCES[$d['source'] ?? ''] ?? 'unknown source'),
            'merged' => 'Received again from '.(self::SOURCES[$d['source'] ?? ''] ?? 'another source').' (duplicate merged)',
            'updated' => "{$who} updated ".collect($d['fields'] ?? [])->map(fn ($f) => str_replace('_id', '', Str::headline($f)))->join(', ', ' and '),
            'auto_detected' => 'Detected '.collect(['lead_type' => 'type', 'branch' => 'branch'])
                ->filter(fn ($label, $key) => isset($d[$key]))
                ->map(fn ($label, $key) => "{$label} {$d[$key]}")
                ->push(isset($d['fields']) ? 'details' : null)
                ->filter()->join(', ').(! empty($d['ai']) ? ' (AI)' : ''),
            'assigned' => "{$who} assigned to ".($d['to_user'] ?? $d['to_branch'] ?? 'nobody')
                .(isset($d['to_user'], $d['to_branch']) ? " ({$d['to_branch']})" : ''),
            'reply_sent' => "{$who} marked the reply as sent",
            'status_changed' => "{$who} changed status from ".(self::STATUSES[$d['from'] ?? ''] ?? '?').' to '.(self::STATUSES[$d['to'] ?? ''] ?? '?')
                .(! empty($d['reason']) ? " – {$d['reason']}" : ''),
            'reminder_sent' => (! empty($d['auto']) ? 'Automatic reminder' : "{$who} sent a reminder").' to '.($d['to'] ?? 'the assignee'),
            'note_added' => "{$who} added a note",
            'call_logged' => "{$who} logged a call (".(self::OUTCOMES[$d['outcome'] ?? ''] ?? 'no outcome').')',
            default => Str::headline($entry->action),
        };
    }
}
