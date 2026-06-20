<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportSchedule;
use Illuminate\Http\Request;

class ReportScheduleController extends Controller
{
    public function index()
    {
        return response()->json(ReportSchedule::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:daily,weekly,monthly,yearly',
            'report_types' => 'required|array',
            'report_types.*' => 'string',
            'phone_numbers' => 'required|string',
            'send_time' => 'required|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,31',
            'is_active' => 'boolean',
        ]);

        $schedule = ReportSchedule::create($validated);
        return response()->json(['message' => 'تم إضافة جدول التقارير', 'schedule' => $schedule], 201);
    }

    public function update(Request $request, ReportSchedule $reportSchedule)
    {
        $validated = $request->validate([
            'type' => 'required|in:daily,weekly,monthly,yearly',
            'report_types' => 'required|array',
            'report_types.*' => 'string',
            'phone_numbers' => 'required|string',
            'send_time' => 'required|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,31',
            'is_active' => 'boolean',
        ]);

        $reportSchedule->update($validated);
        return response()->json(['message' => 'تم تحديث جدول التقارير', 'schedule' => $reportSchedule]);
    }

    public function destroy(ReportSchedule $reportSchedule)
    {
        $reportSchedule->delete();
        return response()->json(['message' => 'تم حذف جدول التقارير']);
    }

    public function sendNow(Request $request, ReportSchedule $reportSchedule)
    {
        // TODO: implement immediate send
        return response()->json(['message' => 'جاري إرسال التقارير...']);
    }
}
