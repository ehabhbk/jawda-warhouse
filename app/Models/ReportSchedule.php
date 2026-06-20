<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    protected $fillable = [
        'type', 'report_types', 'phone_numbers', 'send_time',
        'days', 'day_of_month', 'is_active', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'report_types' => 'array',
            'days' => 'array',
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
            'send_time' => 'string',
        ];
    }
}
