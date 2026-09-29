<?php

namespace App\Http\Requests\Jobs;

use App\Enums\BoxShape;
use App\Enums\BoxType;
use App\Enums\CutFraction;
use App\Enums\Lamination;
use App\Http\Controllers\Jobs\JobEditController;
use App\Models\CuttingDie;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Services\Pricing\BoxPricer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload = the `BoxQuote` emitted by QuickBoxPricingCalculator's
 * onConfirmOrder, plus the customer (and optionally a title / lead).
 *
 * The browser's numbers are only used to make sure the user saw the same
 * price the server computes (BoxPricer); the server's numbers are what gets
 * stored. `pricing()` exposes them to CreateBoxJob after validation.
 */
class StoreBoxJobRequest extends FormRequest
{
    private const UNSAVED_PAPER = 'احفظ نوع الورق والسعر من صفحة أنواع الورق أولاً — الورق المضاف جوه الحاسبة مش بيتحفظ.';

    private const COST_KEYS = ['paperCost', 'platesCost', 'pressRunCost', 'laminationCost', 'dieToolingCost', 'dieCuttingRunCost', 'gluingCost'];

    /** @var array<string, mixed> */
    private array $pricing = [];

    public function authorize(): bool
    {
        // On the update route, the job must still be editable (draft/quoted).
        $job = $this->route('job');

        return $this->user()->can('create-jobs') && ($job === null || JobEditController::editable($job));
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
            'flatWidthMm' => ['required', 'numeric', 'gt:0', 'max:5000'],
            'flatHeightMm' => ['required', 'numeric', 'gt:0', 'max:5000'],
            'interlockPitchMm' => ['nullable', 'numeric', 'gt:0', 'max:5000'],
            'piecesPerBox' => ['required', 'integer', 'min:1', 'max:4'],
            'sheetWidthCm' => ['required', 'integer', 'min:10', 'max:300'],
            'sheetHeightCm' => ['required', 'integer', 'min:10', 'max:300'],
            'cutFraction' => ['required', Rule::enum(CutFraction::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'paperTypeId' => ['required', 'integer', 'exists:paper_types,id'],
            'grammageId' => ['required', 'integer', 'exists:paper_grammages,id'],
            'supplierPriceId' => ['required', 'integer', 'exists:paper_grammage_prices,id'],
            'printColors' => ['required', 'integer', 'min:0', 'max:12'],
            'lamination' => ['required', Rule::enum(Lamination::class)],
            'isUsingExistingDie' => ['required', 'boolean'],
            'dieId' => ['nullable', 'integer', 'exists:dies,id'],
            'interlockEnabled' => ['required', 'boolean'],
            'marginPercent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'baseCostEgp' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'unitPriceEgp' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'totalPriceEgp' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'costBreakdown' => ['required', 'array:'.implode(',', self::COST_KEYS)],
            'costBreakdown.*' => ['required', 'numeric', 'min:0', 'max:1000000000'],
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
            'supplierPriceId.required' => 'الجرام ده مالوش سعر مورد مسجل — ضيفه من صفحة أنواع الورق',
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

            $grammage = PaperGrammage::query()->with('paperType')->find($this->integer('grammageId'));
            if ($grammage->paper_type_id !== $this->integer('paperTypeId')) {
                $validator->errors()->add('grammageId', 'الجرام ده مش تبع نوع الورق المختار');

                return;
            }

            $price = PaperGrammagePrice::query()->find($this->integer('supplierPriceId'));
            if ($price->paper_grammage_id !== $grammage->id) {
                $validator->errors()->add('supplierPriceId', 'السعر ده مش تبع الجرام المختار');

                return;
            }

            $die = null;
            if ($this->boolean('isUsingExistingDie')) {
                $die = $this->filled('dieId') ? CuttingDie::query()->find($this->integer('dieId')) : null;
                if (! $die) {
                    // The calculator starts in "existing die" mode with no die picked — make staff choose.
                    $validator->errors()->add('dieId', 'حدد الاسطمبة من قسم الاسطمبات: «قرب وسعر» أو «تسعير بدون اسطمبه» أو «اسطامبه جديدة»');

                    return;
                }
            }

            $this->pricing = app(BoxPricer::class)->price([
                'flatWidthMm' => $this->float('flatWidthMm'),
                'flatHeightMm' => $this->float('flatHeightMm'),
                'interlockPitchMm' => $this->filled('interlockPitchMm') ? $this->float('interlockPitchMm') : null,
                'piecesPerBox' => $this->integer('piecesPerBox'),
                'quantity' => $this->integer('quantity'),
                'printColors' => $this->integer('printColors'),
                'lamination' => $this->input('lamination'),
                'isUsingExistingDie' => $this->boolean('isUsingExistingDie'),
                'interlockEnabled' => $this->boolean('interlockEnabled'),
                'marginPercent' => $this->float('marginPercent'),
            ], $grammage, $price, $die);

            if (! $this->pricing['fits']) {
                $validator->errors()->add('quantity', 'العلبة بالمقاس ده مش بتدخل الفرخ — راجع المقاسات أو الاسطمبة');

                return;
            }

            // The user must have confirmed the same price the server computes.
            // Allow only rounding noise; anything else means stale prices/settings or tampering.
            $stale = abs($this->pricing['baseCostEgp'] - $this->float('baseCostEgp')) > 0.05
                || abs($this->pricing['unitPriceEgp'] - $this->float('unitPriceEgp')) > 0.005
                || abs($this->pricing['totalPriceEgp'] - $this->float('totalPriceEgp')) > 0.05;

            foreach (self::COST_KEYS as $key) {
                $stale = $stale || abs($this->pricing['costBreakdown'][$key] - (float) $this->input("costBreakdown.{$key}")) > 0.05;
            }

            if ($stale) {
                $validator->errors()->add('totalPriceEgp', 'الأسعار أو ثوابت التسعير اتغيرت من ساعة ما فتحت الصفحة — اعمل تحديث للصفحة وراجع السعر.');
            }
        });
    }

    /**
     * The server-computed price (valid only after validation passed).
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        return $this->pricing;
    }
}
