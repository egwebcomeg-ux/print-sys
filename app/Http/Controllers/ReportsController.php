<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\PaperPriceChange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * التقارير — monthly sales and margins, top customers, waste per press and
 * recent supplier price moves.
 */
class ReportsController extends Controller
{
    private const WON = [JobStatus::Approved, JobStatus::InProduction, JobStatus::Completed, JobStatus::Invoiced];

    public function __invoke(Request $request): Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth()
            : now()->startOfMonth()->toImmutable();
        $from = $month;
        $to = $month->endOfMonth();

        $jobs = Job::query()->with('customer:id,name')->whereBetween('created_at', [$from, $to])->get();
        $won = $jobs->filter(fn (Job $j) => in_array($j->status, self::WON, true));
        $quoted = $jobs->filter(fn (Job $j) => $j->status !== JobStatus::Draft);

        $wonValue = (float) $won->sum('final_price_egp');
        $wonCost = (float) $won->sum('base_cost_egp');

        return Inertia::render('reports/index', [
            'month' => $month->format('Y-m'),
            'summary' => [
                'jobs' => $jobs->count(),
                'quotedCount' => $quoted->count(),
                'quotedValue' => round((float) $quoted->sum('final_price_egp'), 2),
                'wonCount' => $won->count(),
                'wonValue' => round($wonValue, 2),
                'profit' => round($wonValue - $wonCost, 2),
                // Weighted: total profit over total cost of won jobs.
                'avgMargin' => $wonCost > 0 ? round(($wonValue - $wonCost) / $wonCost * 100, 1) : null,
                'conversion' => $quoted->count() > 0 ? round($won->count() / $quoted->count() * 100, 1) : null,
                'invoicedValue' => round((float) $jobs->where('status', JobStatus::Invoiced)->sum('final_price_egp'), 2),
            ],
            'topCustomers' => $won->groupBy('customer_id')
                ->map(fn ($g) => ['name' => $g->first()->customer->name, 'jobs' => $g->count(), 'value' => round((float) $g->sum('final_price_egp'), 2)])
                ->sortByDesc('value')->take(8)->values(),
            'byType' => $won->groupBy(fn (Job $j) => $j->job_type->label())
                ->map(fn ($g, $label) => ['label' => $label, 'jobs' => $g->count(), 'value' => round((float) $g->sum('final_price_egp'), 2)])
                ->values(),
            'waste' => $this->wasteByPress(),
            'priceChanges' => PaperPriceChange::query()
                ->with(['grammage.paperType:id,name', 'supplier:id,name', 'user:id,name'])
                ->latest('id')->limit(25)->get()
                ->map(fn (PaperPriceChange $c) => [
                    'paper' => "{$c->grammage->paperType->name} {$c->grammage->gsm} جم",
                    'supplier' => $c->supplier->name,
                    'old' => $c->old_price_egp !== null ? (float) $c->old_price_egp : null,
                    'new' => (float) $c->new_price_egp,
                    'changePercent' => $c->old_price_egp ? round(((float) $c->new_price_egp - (float) $c->old_price_egp) / (float) $c->old_price_egp * 100, 1) : null,
                    'by' => $c->user?->name,
                    'at' => $c->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Over/under-run per press over the last 6 months of completed jobs:
     * (produced - quoted) / quoted. Negative = short (waste beyond spoilage).
     *
     * @return list<array<string, mixed>>
     */
    private function wasteByPress(): array
    {
        $jobs = Job::query()
            ->whereIn('status', [JobStatus::Completed, JobStatus::Invoiced])
            ->whereNotNull('produced_quantity')->where('quantity', '>', 0)
            ->where('updated_at', '>=', now()->subMonths(6))
            ->with('latestPressAssignment.press:id,name')
            ->get();

        return $jobs->groupBy(fn (Job $j) => $j->latestPressAssignment?->press->name ?? 'بدون مطبعة')
            ->map(function ($group, $press) {
                $deviations = $group->map(fn (Job $j) => ($j->produced_quantity - $j->quantity) / $j->quantity * 100);

                return [
                    'press' => $press,
                    'jobs' => $group->count(),
                    'avgDeviation' => round($deviations->avg(), 1),
                    // Largest miss either way (short or over-run).
                    'worst' => round($deviations->sortByDesc(fn ($d) => abs($d))->first(), 1),
                ];
            })
            ->sortBy('avgDeviation')->values()->all();
    }
}
