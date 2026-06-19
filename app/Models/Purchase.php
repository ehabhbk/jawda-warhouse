<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'user_id',
        'invoice_file',
        'total',
        'tax',
        'discount',
        'grand_total',
        'notes',
        'status',
        'purchase_date',
    ];

    protected $appends = ['invoice_file_url'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function getInvoiceFileUrlAttribute()
    {
        return $this->invoice_file ? asset('storage/' . $this->invoice_file) : null;
    }
}
