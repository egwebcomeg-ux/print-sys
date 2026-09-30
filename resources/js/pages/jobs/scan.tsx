import { Head, Link, router } from '@inertiajs/react';
import { Check, CircleDashed, Loader2 } from 'lucide-react';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import JobStageController from '@/actions/App/Http/Controllers/Jobs/JobStageController';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { num } from '@/lib/format';
import { cn } from '@/lib/utils';

type Stage = {
    id: number;
    name: string;
    status: 'pending' | 'in_progress' | 'done';
};

type ScanJob = {
    id: number;
    name: string;
    customer: string;
    status: string;
    statusLabel: string;
    press: string | null;
    quantity: number | null;
    canUpdateStages: boolean;
    stages: Stage[];
};

/**
 * Opened from the QR on the printed work order: big touch targets to start or
 * finish the current stage from a phone on the floor.
 */
export default function ScanJob({ job }: { job: ScanJob }) {
    const can = useCan();
    const current =
        job.stages.find((s) => s.status === 'in_progress') ??
        job.stages.find((s) => s.status === 'pending');
    const done = job.stages.filter((s) => s.status === 'done').length;
    const editable = job.canUpdateStages && can('run-production');

    const move = (stage: Stage, status: Stage['status']) =>
        router.patch(
            JobStageController.update.url({ job: job.id, stage: stage.id }),
            { status },
            { preserveScroll: true },
        );

    return (
        <div className="mx-auto max-w-md p-4">
            <Head title={`#${job.id} — المراحل`} />

            <div className="mb-4">
                <div className="text-sm text-muted-foreground">
                    #{job.id} · {job.customer}
                </div>
                <h1 className="text-xl font-bold">{job.name}</h1>
                <div className="mt-1 text-sm text-muted-foreground">
                    {job.statusLabel}
                    {job.press && ` · ${job.press}`}
                    {job.quantity && ` · ${num(job.quantity)}`}
                </div>
            </div>

            {current && editable && (
                <div className="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4">
                    <div className="text-sm text-muted-foreground">
                        المرحلة الحالية
                    </div>
                    <div className="mb-3 text-2xl font-bold">
                        {current.name}
                    </div>
                    {current.status === 'pending' ? (
                        <Button
                            size="lg"
                            className="h-14 w-full text-lg"
                            onClick={() => move(current, 'in_progress')}
                        >
                            ابدأ «{current.name}»
                        </Button>
                    ) : (
                        <Button
                            size="lg"
                            className="h-14 w-full text-lg"
                            onClick={() => move(current, 'done')}
                        >
                            <Check /> خلصت «{current.name}»
                        </Button>
                    )}
                </div>
            )}

            {!job.canUpdateStages && (
                <p className="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-300">
                    الشغلانة دي مش في الإنتاج دلوقتي ({job.statusLabel}).
                </p>
            )}
            {job.canUpdateStages && !can('run-production') && (
                <p className="mb-4 text-sm text-muted-foreground">
                    تحديث المراحل لفريق الإنتاج بس.
                </p>
            )}

            <div className="mb-2 text-sm text-muted-foreground" dir="ltr">
                {done}/{job.stages.length}
            </div>
            <ol className="space-y-2">
                {job.stages.map((s) => (
                    <li
                        key={s.id}
                        className={cn(
                            'flex items-center gap-3 rounded-lg border border-border px-4 py-3 text-base',
                            s.id === current?.id && 'border-emerald-500/40',
                        )}
                    >
                        {s.status === 'done' && (
                            <Check className="size-5 text-emerald-400" />
                        )}
                        {s.status === 'in_progress' && (
                            <Loader2 className="size-5 animate-spin text-amber-400" />
                        )}
                        {s.status === 'pending' && (
                            <CircleDashed className="size-5 text-muted-foreground" />
                        )}
                        <span
                            className={cn(
                                s.status === 'done' &&
                                    'text-muted-foreground line-through',
                            )}
                        >
                            {s.name}
                        </span>
                    </li>
                ))}
            </ol>

            <Button asChild variant="ghost" className="mt-6 w-full">
                <Link href={JobController.show(job.id)}>
                    تفاصيل الشغلانة كاملة
                </Link>
            </Button>
        </div>
    );
}
