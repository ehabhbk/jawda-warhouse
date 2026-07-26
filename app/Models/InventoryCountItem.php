<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountItem extends Model
{
    protected $fillable = [
        'count_id',
        'item_id',
        'system_quantity',
        'actual_quantity',
        'difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function count()
    {
        return $this->belongsTo(InventoryCount::class, 'count_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
