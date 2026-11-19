<?php

namespace App\Modules\Leads;

use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\ActivityLog;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;

class LeadCapture
{
    /** The contact's open lead, or a new "New" WhatsApp enquiry lead. */
    public static function leadFor(Contact $contact): Lead
    {
        $open = Lead::where('contact_id', $contact->id)->open()->latest('id')->first();
        if ($open) {
            return $open;
        }

        $lead = Lead::create(['contact_id' => $contact->id, 'source' => LeadSource::WhatsappEnquiry]);
        ActivityLog::record($lead, null, 'created', ['source' => LeadSource::WhatsappEnquiry->value]);

        return $lead;
    }
}
