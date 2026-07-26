<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCount extends Model
{
    protected $fillable = [
        'count_number',
        'warehouse_id',
        'user_id',
        'status',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryCountItem::class, 'count_id');
    }
}
