<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name',
        'support_email',
        'timezone',
        'currency',
        'low_stock_threshold',
    ];
}
