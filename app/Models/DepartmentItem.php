<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentItem extends Model
{
    protected $fillable = [
        'department_id',
        'item_id',
        'quantity',
        'sub_unit_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sub_unit_quantity' => 'integer',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
