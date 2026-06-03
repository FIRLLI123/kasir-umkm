<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppSettingController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = AppSetting::query()->orderBy('setting_key');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->successResponse($query->get(), 'App setting berhasil diambil');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array|min:1',
            'settings.*.setting_key' => [
                'required',
                'string',
                Rule::exists('app_settings', 'setting_key')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->user()->company_id);
                }),
            ],
            'settings.*.setting_value' => 'nullable|string',
            'settings.*.status' => 'nullable|in:00,99',
        ]);

        foreach ($validated['settings'] as $setting) {
            AppSetting::where('setting_key', $setting['setting_key'])->update([
                'setting_value' => isset($setting['setting_value']) ? $setting['setting_value'] : null,
                'status' => isset($setting['status']) ? $setting['status'] : '00',
            ]);
        }

        return $this->successResponse(
            AppSetting::orderBy('setting_key')->get(),
            'App setting berhasil diupdate'
        );
    }
}
