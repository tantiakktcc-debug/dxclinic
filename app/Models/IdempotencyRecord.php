<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'endpoint',
        'request_hash',
        'response_body',
        'status_code',
    ];
}
