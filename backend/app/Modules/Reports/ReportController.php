<?php

namespace App\Modules\Reports;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportBuilder $reports) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-reports');
        [$from, $to] = $this->range($request);

        return response()->json(['data' => [
            'from' => $from->copy()->tz(config('lms.timezone'))->toDateString(),
            'to' => $to->copy()->tz(config('lms.timezone'))->toDateString(),
            ...$this->reports->build($from, $to),
        ]]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('view-reports');
        [$from, $to] = $this->range($request);
        $tz = config('lms.timezone');
        $time = fn ($value) => $value?->copy()->tz($tz)->format('Y-m-d H:i');

        $leadIds = $this->reports->leads($from, $to)->pluck('id');
        $filename = 'funky-monkey-leads-'.$from->copy()->tz($tz)->toDateString().'-to-'.$to->copy()->tz($tz)->toDateString().'.csv';

        return response()->streamDownload(function () use ($leadIds, $time) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Lead ID', 'Created', 'Customer', 'Phone', 'Source', 'Lead type', 'Branch', 'Assignee', 'Status',
                'Reply status', 'Assigned at', 'Reply sent at', 'Minutes to assign', 'Minutes to reply', 'Reminders', 'Converted at', 'Lost reason',
            ]);

            Lead::with(['contact', 'leadType', 'branch', 'assignee'])->whereIn('id', $leadIds)->orderBy('id')
                ->chunk(500, function ($leads) use ($out, $time) {
                    foreach ($leads as $lead) {
                        fputcsv($out, [
                            $lead->id, $time($lead->created_at), $lead->contact->name, $lead->contact->phone, $lead->source->value,
                            $lead->leadType?->name, $lead->branch?->name, $lead->assignee?->name, $lead->status->value,
                            $lead->reply_status?->value, $time($lead->assigned_at), $time($lead->reply_sent_at),
                            $this->reports->minutes($lead->created_at, $lead->assigned_at),
                            $this->reports->minutes($lead->assigned_at, $lead->reply_sent_at),
                            $lead->reminder_count, $time($lead->converted_at), $lead->lost_reason,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon} UTC bounds of whole business-timezone days (default: last 30 days) */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $tz = config('lms.timezone');
        $to = isset($data['to']) ? Carbon::parse($data['to'], $tz) : now($tz);
        $from = isset($data['from']) ? Carbon::parse($data['from'], $tz) : $to->copy()->subDays(29);

        return [$from->startOfDay()->utc(), $to->endOfDay()->utc()];
    }
}
