<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSaleItem extends Model
{
    protected $fillable = [
        'pos_sale_id',
        'item_id',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function posSale()
    {
        return $this->belongsTo(PosSale::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
