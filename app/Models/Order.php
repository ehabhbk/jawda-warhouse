<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'storekeeper_id',
        'notes',
        'rejection_reason',
        'status',
        'warehouse_id',
        'received_at',
        'received_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storekeeper()
    {
        return $this->belongsTo(User::class, 'storekeeper_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
