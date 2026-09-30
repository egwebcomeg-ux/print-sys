<?php

namespace App\Http\Requests\Jobs;

use App\Http\Controllers\Jobs\JobEditController;
use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload = the `ManualJobQuote` emitted by ManualJobCostingCalculator's
 * onConfirmOrder, plus customer and title. Totals are recomputed on the
 * server (CreateManualJob), so only the inputs are trusted.
 */
class StoreManualJobRequest extends FormRequest
{
    private const UNSAVED_PAPER = 'احفظ نوع الورق والسعر من صفحة أنواع الورق أولاً.';

    public function authorize(): bool
    {
        // On the update route, the job must still be editable (draft/quoted).
        $job = $this->route('job');

        return $this->user()->can('create-jobs') && (! $job instanceof Job || JobEditController::editable($job));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'title' => ['required', 'string', 'max:255'],
            'lineItems' => ['required', 'array', 'min:1', 'max:50'],
            'lineItems.*.label' => ['required', 'string', 'max:255'],
            'lineItems.*.paperTypeId' => ['required', 'integer', 'exists:paper_types,id'],
            'lineItems.*.grammageId' => ['required', 'integer', 'exists:paper_grammages,id'],
            'lineItems.*.supplierPriceId' => ['nullable', 'integer', 'exists:paper_grammage_prices,id'],
            'lineItems.*.sheetWidthCm' => ['required', 'numeric', 'gt:0', 'max:999'],
            'lineItems.*.sheetHeightCm' => ['required', 'numeric', 'gt:0', 'max:999'],
            'lineItems.*.sheetsCount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'costLines' => ['present', 'array', 'max:50'],
            'costLines.*.label' => ['nullable', 'string', 'max:255'],
            'costLines.*.amountEgp' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'marginPercent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'producedQuantity' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'finalPriceEgp' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'اختار العميل الأول',
            'title.required' => 'اكتب اسم الشغلانة (مثال: كتيب 16 صفحة)',
            'lineItems.*.paperTypeId.*' => self::UNSAVED_PAPER,
            'lineItems.*.grammageId.*' => self::UNSAVED_PAPER,
            'lineItems.*.supplierPriceId.*' => self::UNSAVED_PAPER,
        ];
    }

    protected function prepareForValidation(): void
    {
        // The calculator uses '' for "no price picked" — treat it as null.
        $items = collect($this->array('lineItems'))->map(function ($item) {
            if (is_array($item) && ($item['supplierPriceId'] ?? null) === '') {
                $item['supplierPriceId'] = null;
            }

            return $item;
        });

        $this->merge(['lineItems' => $items->all()]);
    }
}
