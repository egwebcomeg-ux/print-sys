<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Pricing constants, taken from QuickBoxPricingCalculator.tsx. Every value is
 * a PLACEHOLDER until the factory confirms real rates — edit them from
 * الإعدادات ← ثوابت التسعير. Existing values are never overwritten.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['spoilage_rate', 0.03, 'نسبة الهالك (من 0 لـ 1)', 'قيمة مبدئية — +3% أفرخ زيادة لهالك الماكينة'],
            ['new_die_cost_egp', 650, 'تكلفة اسطمبة جديدة (ج)', 'قيمة مبدئية'],
            ['plate_cost_per_color_egp', 150, 'تكلفة الزنك لكل لون (ج)', 'قيمة مبدئية'],
            ['press_run_rate_per_color_per_1000_sheets_egp', 180, 'تكلفة الطباعة لكل لون لكل 1000 فرخ مقصوص (ج)', 'قيمة مبدئية'],
            ['lamination_rate_per_sheet_egp', ['matte' => 0.35, 'gloss' => 0.30], 'تكلفة السلوفان للفرخ المقصوص (ج)', 'قيمة مبدئية — مط / لامع'],
            ['die_cut_rate_per_sheet_egp', 0.15, 'تكلفة التكسير للفرخ المقصوص (ج)', 'قيمة مبدئية'],
            ['glue_fold_rate_per_unit_egp', 0.05, 'تكلفة اللصق والتطبيق للعلبة (ج)', 'قيمة مبدئية'],
            ['billing_tolerance_percent', 10, 'سماحية الفوترة (%)', 'لو الكمية الفعلية في حدود النسبة دي من المطلوبة، الفاتورة بسعر العرض زي ما هو؛ غير كده بتتحسب بسعر القطعة × الفعلي'],
            ['default_margin_percent', 20, 'نسبة الربح المبدئية في الحاسبة (%)', 'مجرد قيمة مبدئية — الموظف بيكتب النسبة لكل شغلانة'],
        ];

        foreach ($rows as $order => [$key, $value, $label, $note]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'pricing', 'label_ar' => $label, 'note' => $note, 'sort_order' => $order],
            );
        }

        // Company details printed on quotes (الإعدادات ← بيانات المصنع).
        $company = [
            ['company_name', 'Pantopack', 'اسم المصنع'],
            ['company_address', '', 'العنوان'],
            ['company_phone', '', 'التليفون'],
            ['company_email', '', 'الإيميل'],
            ['company_tax_id', '', 'رقم التسجيل الضريبي'],
            ['quote_vat_percent', 14, 'نسبة الضريبة على عرض السعر (%)'],
            ['quote_validity_days', 15, 'مدة صلاحية عرض السعر (يوم)'],
            ['quote_notes', 'الأسعار لا تشمل التوصيل.
يبدأ التنفيذ بعد اعتماد التصميم ودفع مقدم.', 'ملاحظات ثابتة أسفل عرض السعر'],
        ];

        foreach ($company as $order => [$key, $value, $label]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'company', 'label_ar' => $label, 'sort_order' => $order],
            );
        }

        Settings::forget();
    }
}
