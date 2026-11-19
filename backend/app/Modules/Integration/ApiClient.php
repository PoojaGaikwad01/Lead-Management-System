<?php

namespace App\Modules\Integration;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/** A system (e.g. the Funky Monkey portal) allowed to read leads through /api/v1 with a bearer token. */
#[Fillable(['name', 'active'])]
class ApiClient extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens;

    public const ABILITY = 'leads:read';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
