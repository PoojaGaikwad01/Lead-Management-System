<?php

namespace App\Modules\Integration;

use App\Modules\Leads\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The lead shape shared by the portal API and outgoing webhooks. @mixin Lead */
class PortalLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'lead_type' => $this->leadType?->name,
            'branch' => $this->branch?->name,
            'customer' => ['name' => $this->contact->name, 'phone' => $this->contact->phone],
            'details' => (object) ($this->custom_data ?? []),
            'assigned_to' => $this->assignee?->name,
            'lost_reason' => $this->lost_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
