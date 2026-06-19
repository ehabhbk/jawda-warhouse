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
}
