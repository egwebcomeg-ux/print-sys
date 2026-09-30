<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company details printed on quotes (name, address, tax id, VAT, validity).
 */
class CompanySettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/company-settings', [
            'settings' => Setting::query()->where('group', 'company')->orderBy('sort_order')->get()
                ->map(fn (Setting $s) => ['key' => $s->key, 'label' => $s->label_ar, 'value' => $s->value]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:100'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:100'],
            'quote_vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'quote_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'quote_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($data as $key => $value) {
            $value = match ($key) {
                'quote_vat_percent' => (float) $value,
                'quote_validity_days' => (int) $value,
                default => (string) ($value ?? ''),
            };
            Setting::query()->whereKey($key)->update(['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }

        Settings::forget();
        ActivityLog::record('settings', null, 'updated', 'تعديل بيانات المصنع', $data);
        $this->toast('تم حفظ بيانات المصنع');

        return back();
    }
}
