<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ClosureType;
use App\Enums\CutFraction;
use App\Enums\DieCondition;
use App\Http\Controllers\Controller;
use App\Models\CuttingDie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CuttingDieController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dies/index', [
            'dies' => CuttingDie::query()->orderBy('code')->get()->map(fn (CuttingDie $die) => [
                ...$die->only(['id', 'code', 'name', 'rack_location', 'ups_on_cut_sheet', 'jobs_run_count', 'estimated_lifespan_jobs']),
                'dimensions' => sprintf('%s×%s×%s', (float) $die->length_cm, (float) $die->width_cm, (float) $die->depth_cm),
                'closure' => $die->closure_type->label(),
                'cut_fraction' => $die->cut_fraction->value,
                'condition' => $die->condition->value,
                'conditionLabel' => $die->condition->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dies/form', ['die' => null, ...$this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        CuttingDie::query()->create($this->validated($request));
        $this->toast('تم إضافة الاسطمبة');

        return to_route('dies.index');
    }

    public function edit(CuttingDie $die): Response
    {
        return Inertia::render('dies/form', ['die' => $die, ...$this->options()]);
    }

    public function update(Request $request, CuttingDie $die): RedirectResponse
    {
        $die->update($this->validated($request, $die));
        $this->toast('تم حفظ الاسطمبة');

        return to_route('dies.index');
    }

    public function destroy(CuttingDie $die): RedirectResponse
    {
        // Jobs keep their row (die_id is nullOnDelete).
        $die->delete();
        $this->toast('تم مسح الاسطمبة');

        return to_route('dies.index');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'closureTypes' => ClosureType::options(),
            'cutFractions' => CutFraction::options(),
            'conditions' => DieCondition::options(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CuttingDie $die = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('dies', 'code')->ignore($die)],
            'name' => ['required', 'string', 'max:255'],
            'length_cm' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'width_cm' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'depth_cm' => ['required', 'numeric', 'min:0', 'max:999'],
            'closure_type' => ['required', Rule::enum(ClosureType::class)],
            'rack_location' => ['nullable', 'string', 'max:255'],
            'ups_on_cut_sheet' => ['required', 'integer', 'min:1', 'max:500'],
            'cut_fraction' => ['required', Rule::enum(CutFraction::class)],
            'condition' => ['required', Rule::enum(DieCondition::class)],
            'jobs_run_count' => ['nullable', 'integer', 'min:0'],
            'estimated_lifespan_jobs' => ['nullable', 'integer', 'min:1'],
        ]) + ['jobs_run_count' => 0];
    }
}
