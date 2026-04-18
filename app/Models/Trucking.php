<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trucking extends Model
{
    protected $fillable = [
        'driver_name',
        'delivery_address',
        'status',
    ];
}
