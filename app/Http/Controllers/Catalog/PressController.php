<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\CutFraction;
use App\Enums\PaperCategory;
use App\Http\Controllers\Controller;
use App\Models\Press;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PressController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('presses/index', [
            'presses' => Press::query()->orderByDesc('is_internal')->orderBy('name')->get(),
            'cutFractions' => CutFraction::options(),
            'paperCategories' => PaperCategory::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('presses/form', ['press' => null, ...$this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Press::query()->create($this->validated($request));
        $this->toast('تم إضافة المطبعة');

        return to_route('presses.index');
    }

    public function edit(Press $press): Response
    {
        return Inertia::render('presses/form', ['press' => $press, ...$this->options()]);
    }

    public function update(Request $request, Press $press): RedirectResponse
    {
        $press->update($this->validated($request));
        $this->toast('تم حفظ المطبعة');

        return to_route('presses.index');
    }

    public function destroy(Press $press): RedirectResponse
    {
        if ($this->deleteSafely(fn () => $press->delete(), 'مينفعش تمسح مطبعة اتوزع عليها شغل قبل كده')) {
            $this->toast('تم مسح المطبعة');
        }

        return to_route('presses.index');
    }

    /**
     * Manual capacity indicator ("how many days of work are queued"),
     * edited inline from the routing screen until real scheduling exists.
     */
    public function updateBacklog(Request $request, Press $press): RedirectResponse
    {
        $data = $request->validate([
            'current_backlog_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $press->update($data);

        return back();
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'cutFractions' => CutFraction::options(),
            'paperCategories' => PaperCategory::options(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_internal' => ['boolean'],
            'max_colors' => ['required', 'integer', 'min:1', 'max:12'],
            'supported_cut_fractions' => ['required', 'array', 'min:1'],
            'supported_cut_fractions.*' => [Rule::enum(CutFraction::class)],
            'supported_paper_categories' => ['nullable', 'array'],
            'supported_paper_categories.*' => [Rule::enum(PaperCategory::class)],
            'current_backlog_days' => ['required', 'integer', 'min:0', 'max:365'],
            'contact_note' => ['nullable', 'string', 'max:255'],
        ]);

        // Empty list = accepts every paper category (see PressRoutingSelector).
        $data['supported_paper_categories'] = array_values($data['supported_paper_categories'] ?? []);
        $data['is_internal'] = (bool) ($data['is_internal'] ?? false);

        return $data;
    }
}
