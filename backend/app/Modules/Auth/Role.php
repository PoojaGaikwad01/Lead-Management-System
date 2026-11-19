<?php

namespace App\Modules\Auth;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Assignee = 'assignee';
}
