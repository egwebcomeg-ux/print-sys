<?php

/*
| Arabic validation messages. Rules not listed here fall back to English
| (APP_FALLBACK_LOCALE=en).
*/

return [

    'accepted' => 'لازم توافق على :attribute.',
    'array' => ':attribute لازم يكون قائمة.',
    'boolean' => ':attribute لازم يكون نعم أو لا.',
    'confirmed' => 'تأكيد :attribute مش مطابق.',
    'current_password' => 'الباسورد غلط.',
    'date' => ':attribute مش تاريخ صحيح.',
    'decimal' => ':attribute لازم يكون فيه :decimal أرقام عشرية.',
    'different' => ':attribute و :other لازم يكونوا مختلفين.',
    'email' => ':attribute لازم يكون إيميل صحيح.',
    'enum' => 'القيمة المختارة في :attribute مش صحيحة.',
    'exists' => 'القيمة المختارة في :attribute مش موجودة.',
    'filled' => ':attribute لازم يكون فيه قيمة.',
    'gt' => [
        'numeric' => ':attribute لازم يكون أكبر من :value.',
        'string' => ':attribute لازم يكون أطول من :value حرف.',
        'array' => ':attribute لازم يكون فيه أكتر من :value عنصر.',
    ],
    'gte' => [
        'numeric' => ':attribute لازم يكون :value أو أكتر.',
        'string' => ':attribute لازم يكون :value حرف أو أكتر.',
        'array' => ':attribute لازم يكون فيه :value عنصر أو أكتر.',
    ],
    'in' => 'القيمة المختارة في :attribute مش صحيحة.',
    'integer' => ':attribute لازم يكون رقم صحيح.',
    'json' => ':attribute لازم يكون JSON صحيح.',
    'lt' => [
        'numeric' => ':attribute لازم يكون أقل من :value.',
        'string' => ':attribute لازم يكون أقصر من :value حرف.',
        'array' => ':attribute لازم يكون فيه أقل من :value عنصر.',
    ],
    'max' => [
        'array' => ':attribute مينفعش يكون فيه أكتر من :max عنصر.',
        'file' => ':attribute مينفعش يكون أكبر من :max كيلوبايت.',
        'numeric' => ':attribute مينفعش يكون أكبر من :max.',
        'string' => ':attribute مينفعش يكون أطول من :max حرف.',
    ],
    'min' => [
        'array' => ':attribute لازم يكون فيه :min عنصر على الأقل.',
        'file' => ':attribute لازم يكون :min كيلوبايت على الأقل.',
        'numeric' => ':attribute لازم يكون :min على الأقل.',
        'string' => ':attribute لازم يكون :min حرف على الأقل.',
    ],
    'numeric' => ':attribute لازم يكون رقم.',
    'password' => [
        'letters' => ':attribute لازم يكون فيه حرف واحد على الأقل.',
        'mixed' => ':attribute لازم يكون فيه حرف كبير وحرف صغير على الأقل.',
        'numbers' => ':attribute لازم يكون فيه رقم واحد على الأقل.',
        'symbols' => ':attribute لازم يكون فيه رمز واحد على الأقل.',
        'uncompromised' => ':attribute ده ظهر في تسريب بيانات — اختار واحد تاني.',
    ],
    'present' => ':attribute لازم يكون موجود.',
    'prohibited' => ':attribute مش مسموح.',
    'regex' => 'صيغة :attribute مش صحيحة.',
    'required' => ':attribute مطلوب.',
    'required_if' => ':attribute مطلوب لما :other يكون :value.',
    'required_with' => ':attribute مطلوب لما :values موجود.',
    'same' => ':attribute و :other لازم يكونوا زي بعض.',
    'string' => ':attribute لازم يكون نص.',
    'unique' => ':attribute ده مستخدم قبل كده.',
    'url' => ':attribute لازم يكون لينك صحيح.',

    /*
    | Field names shown in messages (instead of the raw input name).
    */
    'attributes' => [
        'name' => 'الاسم',
        'email' => 'الإيميل',
        'password' => 'الباسورد',
        'phone' => 'التليفون',
        'role' => 'الصلاحية',
        'notes' => 'الملاحظات',
        'title' => 'اسم الشغلانة',
        'status' => 'الحالة',
        'code' => 'الكود',
        'gsm' => 'الجرام',
        'category' => 'الفئة',
        'customer_id' => 'العميل',
        'contact_name' => 'اسم جهة الاتصال',
        'company_name' => 'الشركة',
        'credit_limit_egp' => 'حد الائتمان',
        'contact_note' => 'التواصل',
        'sheet_width_cm' => 'عرض الفرخ',
        'sheet_height_cm' => 'طول الفرخ',
        'paper_supplier_id' => 'المورد',
        'price_per_ton_egp' => 'سعر الطن',
        'length_cm' => 'الطول',
        'width_cm' => 'العرض',
        'depth_cm' => 'الارتفاع',
        'closure_type' => 'نوع القفل',
        'ups_on_cut_sheet' => 'عدد العلب في الشابلونة',
        'cut_fraction' => 'مقاس الفرخ',
        'condition' => 'الحالة',
        'max_colors' => 'أقصى عدد ألوان',
        'supported_cut_fractions' => 'مقاسات الفرخ',
        'current_backlog_days' => 'الشغل اللي قدامها',
        'produced_quantity' => 'الكمية الفعلية',
        'odoo_invoice_id' => 'رقم الفاتورة',
        'expected_quantity' => 'الكمية المتوقعة',
        'marginPercent' => 'نسبة الربح',
        'quantity' => 'الكمية',
        'printColors' => 'عدد الألوان',
    ],

];
