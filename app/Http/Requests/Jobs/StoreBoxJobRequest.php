<?php

namespace App\Http\Requests\Jobs;

use App\Enums\BoxShape;
use App\Enums\BoxType;
use App\Enums\Lamination;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload = the `BoxQuote` emitted by QuickBoxPricingCalculator's
 * onConfirmOrder, plus the customer (and optionally a title / lead).
 */
class StoreBoxJobRequest extends FormRequest
{
    private const UNSAVED_PAPER = 'احفظ نوع الورق والسعر من صفحة أنواع الورق أولاً — الورق المضاف جوه الحاسبة مش بيتحفظ.';

    public function authorize(): bool
    {
        return $this->user()->can('create-jobs');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'boxType' => ['required', Rule::enum(BoxType::class)],
            'shape' => ['required', Rule::enum(BoxShape::class)],
            'dimensions.lengthCm' => ['required', 'numeric', 'gt:0', 'max:999'],
            'dimensions.widthCm' => ['required', 'numeric', 'gt:0', 'max:999'],
            'dimensions.depthCm' => ['required', 'numeric', 'min:0', 'max:999'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'paperTypeId' => ['required', 'integer', 'exists:paper_types,id'],
            'grammageId' => ['required', 'integer', 'exists:paper_grammages,id'],
            'supplierPriceId' => ['nullable', 'integer', 'exists:paper_grammage_prices,id'],
            'pricePerTonEgp' => ['nullable', 'numeric', 'min:0'],
            'printColors' => ['required', 'integer', 'min:0', 'max:12'],
            'lamination' => ['required', Rule::enum(Lamination::class)],
            'isUsingExistingDie' => ['required', 'boolean'],
            'dieId' => ['nullable', 'integer', 'exists:dies,id'],
            'rawSheetsNeeded' => ['required', 'integer', 'min:0'],
            'upsPerRawSheet' => ['required', 'integer', 'min:1'],
            'interlocked' => ['required', 'boolean'],
            'marginPercent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'baseCostEgp' => ['required', 'numeric', 'min:0'],
            'unitPriceEgp' => ['required', 'numeric', 'min:0'],
            'totalPriceEgp' => ['required', 'numeric', 'min:0'],
            'costBreakdown' => ['required', 'array'],
            'costBreakdown.*' => ['numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'اختار العميل الأول',
            'paperTypeId.integer' => self::UNSAVED_PAPER,
            'paperTypeId.exists' => self::UNSAVED_PAPER,
            'grammageId.integer' => self::UNSAVED_PAPER,
            'grammageId.exists' => self::UNSAVED_PAPER,
            'supplierPriceId.integer' => self::UNSAVED_PAPER,
            'supplierPriceId.exists' => self::UNSAVED_PAPER,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $grammage = PaperGrammage::query()->find($this->integer('grammageId'));
            if ($grammage->paper_type_id !== $this->integer('paperTypeId')) {
                $validator->errors()->add('grammageId', 'الجرام ده مش تبع نوع الورق المختار');
            }

            if ($this->filled('supplierPriceId')) {
                $price = PaperGrammagePrice::query()->find($this->integer('supplierPriceId'));
                if ($price->paper_grammage_id !== $grammage->id) {
                    $validator->errors()->add('supplierPriceId', 'السعر ده مش تبع الجرام المختار');
                }
            }

            if ($this->boolean('isUsingExistingDie') && ! $this->filled('dieId')) {
                // The calculator starts in "existing die" mode with no die picked,
                // which prices without any die cost — make staff choose explicitly.
                $validator->errors()->add('dieId', 'حدد الاسطمبة من قسم الاسطمبات: «قرب وسعر» أو «تسعير بدون اسطمبه» أو «اسطامبه جديدة»');
            }

            // The box math runs in the calculator by design; make sure the
            // numbers it sent are at least internally consistent.
            $breakdownTotal = array_sum(array_map('floatval', $this->input('costBreakdown', [])));
            if (abs($breakdownTotal - (float) $this->input('baseCostEgp')) > 1) {
                $validator->errors()->add('baseCostEgp', 'بنود التكلفة مش مساوية لإجمالي التكلفة');
            }

            $expectedTotal = (float) $this->input('baseCostEgp') * (1 + (float) $this->input('marginPercent') / 100);
            $tolerance = 1 + 0.005 * $this->integer('quantity'); // unit price is rounded to 2 decimals
            if (abs($expectedTotal - (float) $this->input('totalPriceEgp')) > $tolerance) {
                $validator->errors()->add('totalPriceEgp', 'السعر النهائي مش مطابق للتكلفة + نسبة الربح');
            }
        });
    }
}
