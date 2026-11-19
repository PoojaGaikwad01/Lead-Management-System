<?php

namespace App\Modules\Integration;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Token-secured read API for the existing Funky Monkey portal (/api/v1). */
class PortalLeadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $leads = Lead::with(['contact', 'leadType', 'branch', 'assignee'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['updated_since'] ?? null, fn ($q, $since) => $q->where('updated_at', '>=', now()->parse($since)->utc()))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 50);

        return PortalLeadResource::collection($leads);
    }

    public function show(Lead $lead): PortalLeadResource
    {
        return new PortalLeadResource($lead->load(['contact', 'leadType', 'branch', 'assignee']));
    }
}
