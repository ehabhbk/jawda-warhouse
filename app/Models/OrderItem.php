<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'item_id',
        'quantity',
        'price',
        'fulfilled_quantity',
        'unit_type',
        'sub_unit_name',
    ];

    protected $appends = ['remaining_quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'fulfilled_quantity' => 'integer',
            'price' => 'decimal:2',
            'unit_type' => 'string',
        ];
    }

    public function getRemainingQuantityAttribute(): int
    {
        return $this->quantity - $this->fulfilled_quantity;
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
