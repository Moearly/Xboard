<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    use BelongsToTenant;
    protected $table = 'v2_invite_code';
    protected $dateFormat = 'U';
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'status' => 'boolean',
    ];

    const STATUS_UNUSED = 0;
    const STATUS_USED = 1;
}
