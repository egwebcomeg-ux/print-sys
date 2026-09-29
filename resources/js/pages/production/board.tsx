import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Factory, Loader2 } from 'lucide-react';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import ProductionBoardController from '@/actions/App/Http/Controllers/ProductionBoardController';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { JobStatusBadge } from '@/components/jobs/status-badge';
import { Badge } from '@/components/ui/badge';
import { num } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { JobStatus } from '@/types';

type BoardJob = {
    id: number;
    name: string;
    customer: string;
    quantity: number | null;
    status: JobStatus;
    statusLabel: string;
    currentStage: string | null;
    currentStageStatus: 'pending' | 'in_progress' | 'done' | null;
    stagesDone: number;
    stagesTotal: number;
    daysSinceApproval: number;
};

type PressColumn = {
    id: number;
    name: string;
    isInternal: boolean;
    backlogDays: number;
    jobs: BoardJob[];
};

function JobCard({ job }: { job: BoardJob }) {
    const progress = job.stagesTotal
        ? Math.round((job.stagesDone / job.stagesTotal) * 100)
        : 0;

    return (
        <Link
            href={JobController.show(job.id)}
            className="block rounded-lg border border-border bg-card p-3 transition-colors hover:border-emerald-500/40"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="truncate text-sm font-medium">
                        #{job.id} {job.name}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {job.customer} · {num(job.quantity)}
                    </div>
                </div>
                <JobStatusBadge status={job.status} label={job.statusLabel} />
            </div>
            <div className="mt-2 flex items-center justify-between gap-2 text-xs">
                <span className="flex items-center gap-1">
                    {job.currentStageStatus === 'in_progress' && (
                        <Loader2 className="size-3 animate-spin text-amber-400" />
                    )}
                    {job.currentStage ?? 'كل المراحل خلصت'}
                </span>
                <span className="text-muted-foreground" dir="ltr">
                    {job.stagesDone}/{job.stagesTotal}
                </span>
            </div>
            <div className="mt-1.5 h-1 overflow-hidden rounded bg-secondary">
                <div
                    className="h-full bg-emerald-500"
                    style={{ width: `${progress}%` }}
                />
            </div>
            {job.daysSinceApproval >= 7 && (
                <div className="mt-2 flex items-center gap-1 text-xs text-amber-400">
                    <AlertTriangle className="size-3" /> {job.daysSinceApproval}{' '}
                    يوم بدون تحديث
                </div>
            )}
        </Link>
    );
}

export default function ProductionBoard({
    presses,
    unassigned,
}: {
    presses: PressColumn[];
    unassigned: BoardJob[];
}) {
    const active = presses.filter((p) => p.jobs.length > 0);

    return (
        <PageBody>
            <Head title="لوحة الإنتاج" />
            <PageHeader
                title="لوحة الإنتاج"
                description="الشغل الموافق عليه واللي في الإنتاج، مقسّم على المطابع بالمرحلة الحالية"
            />

            {unassigned.length > 0 && (
                <section className="mb-6">
                    <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-300">
                        <AlertTriangle className="size-4" /> لسه متوزعتش على
                        مطبعة ({unassigned.length})
                    </h2>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        {unassigned.map((job) => (
                            <JobCard key={job.id} job={job} />
                        ))}
                    </div>
                </section>
            )}

            {active.length === 0 && unassigned.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    مفيش شغل في الإنتاج دلوقتي.
                </p>
            )}

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {active.map((press) => (
                    <section
                        key={press.id}
                        className="rounded-xl border border-border bg-background/40 p-3"
                    >
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <Factory className="size-4 text-muted-foreground" />{' '}
                                {press.name}
                            </h2>
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={
                                        press.isInternal
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {press.isInternal ? 'داخلية' : 'مقاول'}
                                </Badge>
                                <span
                                    className={cn(
                                        'text-xs',
                                        press.backlogDays >= 5
                                            ? 'text-amber-400'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {press.backlogDays} يوم شغل
                                </span>
                            </div>
                        </div>
                        <div className="space-y-2">
                            {press.jobs.map((job) => (
                                <JobCard key={job.id} job={job} />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </PageBody>
    );
}

ProductionBoard.layout = {
    breadcrumbs: [{ title: 'لوحة الإنتاج', href: ProductionBoardController() }],
};
