<?php

namespace App\Modules\Leads\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Converted = 'converted';
    case Lost = 'lost';
    case Closed = 'closed';
}
