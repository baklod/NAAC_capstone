<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRevenueLog extends Model
{
    protected $fillable = [
        'inventory_id',
        'branch_id',
        'product_id',
        'batch_number',
        'quantity',
        'price',
        'expected_revenue',
        'action',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'expected_revenue' => 'decimal:2',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
