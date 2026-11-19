<?php

namespace App\Modules\Leads\Enums;

enum ReplyStatus: string
{
    case NotSent = 'not_sent';
    case Sent = 'sent';
}
