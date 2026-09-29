<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobPaperItem;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printable quote (عرض سعر) for the customer — the browser's "save as PDF"
 * renders Arabic reliably, unlike server-side PDF libraries.
 */
class JobQuoteController extends Controller
{
    public function show(Job $job): Response
    {
        $job->load(['customer', 'paperGrammage.paperType', 'paperItems.paperGrammage.paperType']);

        $company = Settings::company();
        $subtotal = (float) $job->final_price_egp;
        $vat = round($subtotal * $company['vatPercent'] / 100, 2);

        $spec = [];
        if ($job->job_type === JobType::Box) {
            $spec = array_filter([
                'النوع' => $job->box_type?->label().' — '.$job->box_shape?->label(),
                'المقاس (سم)' => sprintf('%s × %s × %s', (float) $job->length_cm, (float) $job->width_cm, (float) $job->depth_cm),
                'الورق' => $job->paperGrammage ? "{$job->paperGrammage->paperType->name} {$job->paperGrammage->gsm} جم" : null,
                'الطباعة' => $job->print_colors === 0 ? 'سادة' : "{$job->print_colors} لون",
                'السلوفان' => $job->lamination->label(),
            ]);
        }

        return Inertia::render('print/quote', [
            'company' => $company,
            'quote' => [
                'number' => sprintf('Q-%05d', $job->id),
                'date' => now()->toDateString(),
                'validUntil' => now()->addDays($company['validityDays'])->toDateString(),
                'customer' => $job->customer->only(['name', 'phone', 'email']),
                'title' => $job->displayName(),
                'spec' => $spec,
                'quantity' => $job->quantity,
                'unitPrice' => $job->quantity ? round($subtotal / $job->quantity, 2) : null,
                'paperItems' => $job->paperItems->map(fn (JobPaperItem $i) => [
                    'label' => $i->label,
                    'paper' => "{$i->paperGrammage->paperType->name} {$i->paperGrammage->gsm} جم",
                    'size' => sprintf('%s × %s سم', (float) $i->sheet_width_cm, (float) $i->sheet_height_cm),
                    'sheets' => $i->sheets_count,
                ]),
                'subtotal' => $subtotal,
                'vatPercent' => $company['vatPercent'],
                'vat' => $vat,
                'total' => round($subtotal + $vat, 2),
                'notes' => $company['notes'],
                'preparedBy' => request()->user()?->name,
            ],
        ]);
    }
}
