<?php

namespace App\Modules\Leads\Enums;

enum LeadSource: string
{
    case WhatsappEnquiry = 'whatsapp_enquiry';
    case AgencyText = 'agency_text';
    case AgencyFile = 'agency_file';
    case ManualUpload = 'manual_upload';
}
