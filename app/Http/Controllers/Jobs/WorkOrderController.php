<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStageStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobPaperItem;
use App\Models\JobStage;
use App\Support\QrCode;
use Inertia\Inertia;
use Inertia\Response;

/**
 * أمر الشغل — printable production ticket, and the phone page its QR opens.
 */
class WorkOrderController extends Controller
{
    /** Printable A4 work order for the production floor. */
    public function show(Job $job): Response
    {
        $job->load(['customer', 'die', 'paperGrammage.paperType', 'paperItems.paperGrammage.paperType', 'stages', 'latestPressAssignment.press']);
        $plan = $job->quote_snapshot['pricing'] ?? [];

        return Inertia::render('print/work-order', [
            'order' => [
                'number' => sprintf('WO-%05d', $job->id),
                'jobId' => $job->id,
                'name' => $job->displayName(),
                'customer' => $job->customer->name,
                'status' => $job->status->label(),
                'printedAt' => now()->toDateTimeString('minute'),
                'quantity' => $job->quantity,
                'press' => $job->latestPressAssignment?->press->name,
                'box' => $job->job_type === JobType::Box ? [
                    'type' => $job->box_type?->label().' — '.$job->box_shape?->label(),
                    'dimensions' => sprintf('%s × %s × %s سم', (float) $job->length_cm, (float) $job->width_cm, (float) $job->depth_cm),
                    'paper' => $job->paperGrammage ? "{$job->paperGrammage->paperType->name} {$job->paperGrammage->gsm} جم" : null,
                    'sheet' => isset($plan['sheetWidthCm']) ? "{$plan['sheetWidthCm']} × {$plan['sheetHeightCm']} سم — ".self::cutLabel($plan['cutFraction'] ?? null) : null,
                    'rawSheets' => $job->raw_sheets_needed,
                    'ups' => $job->ups_per_raw_sheet,
                    'piecesPerBox' => $plan['piecesPerBox'] ?? 1,
                    'interlocked' => $job->interlocked,
                    'printColors' => $job->print_colors,
                    'lamination' => $job->lamination->label(),
                    'die' => $job->die ? "{$job->die->code} — {$job->die->name}" : ($job->is_using_existing_die ? null : 'اسطمبة جديدة'),
                    'dieLocation' => $job->die?->rack_location,
                ] : null,
                'paperItems' => $job->paperItems->map(fn (JobPaperItem $i) => [
                    'label' => $i->label,
                    'paper' => "{$i->paperGrammage->paperType->name} {$i->paperGrammage->gsm} جم",
                    'size' => sprintf('%s × %s سم', (float) $i->sheet_width_cm, (float) $i->sheet_height_cm),
                    'sheets' => $i->sheets_count,
                ]),
                'stages' => $job->stages->map(fn (JobStage $s) => ['name' => $s->name, 'done' => $s->status === JobStageStatus::Done]),
            ],
            // The QR opens the phone page for this job (staff must be logged in).
            'qrSvg' => QrCode::svg(route('jobs.scan', $job)),
        ]);
    }

    /** Phone page opened from the QR: current stage and one-tap start/finish. */
    public function scan(Job $job): Response
    {
        $job->load(['customer', 'stages', 'latestPressAssignment.press']);

        return Inertia::render('jobs/scan', [
            'job' => [
                'id' => $job->id,
                'name' => $job->displayName(),
                'customer' => $job->customer->name,
                'status' => $job->status->value,
                'statusLabel' => $job->status->label(),
                'press' => $job->latestPressAssignment?->press->name,
                'quantity' => $job->quantity,
                'canUpdateStages' => in_array($job->status, [JobStatus::Approved, JobStatus::InProduction], true),
                'stages' => $job->stages->map(fn (JobStage $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'status' => $s->status->value,
                ]),
            ],
        ]);
    }

    private static function cutLabel(?string $fraction): string
    {
        return match ($fraction) {
            '1/1' => 'فرخ كامل',
            '1/2' => 'نص فرخ',
            '1/4' => 'ربع فرخ',
            '1/6' => 'سدس فرخ',
            '1/8' => 'تمن فرخ',
            default => '',
        };
    }
}
