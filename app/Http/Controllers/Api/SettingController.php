<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group')->map(function ($items) {
            return $items->mapWithKeys(fn ($s) => [$s['key'] => $s['value']]);
        });
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            '*.key' => 'required|string',
            '*.value' => 'nullable',
            '*.group' => 'required|string',
        ]);

        foreach ($request->all() as $item) {
            Setting::setValue($item['key'], $item['value'], $item['group']);
        }

        $settings = Setting::all()->groupBy('group')->map(function ($items) {
            return $items->mapWithKeys(fn ($s) => [$s['key'] => $s['value']]);
        });

        return response()->json(['message' => 'تم حفظ الإعدادات', 'settings' => $settings]);
    }
}
