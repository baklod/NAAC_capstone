<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'branch_id',
        'first_name',
        'last_name',
        'contact_number',
        'contact_email',
        'address',
        'profile_picture',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

