import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    CircleDashed,
    FileText,
    Loader2,
    Pencil,
    QrCode,
    RefreshCw,
} from 'lucide-react';
import { useMemo, useRef } from 'react';
import type { ReactNode } from 'react';
import PressController from '@/actions/App/Http/Controllers/Catalog/PressController';
import JobCompletionController from '@/actions/App/Http/Controllers/Jobs/JobCompletionController';
import CustomerController from '@/actions/App/Http/Controllers/Catalog/CustomerController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import JobEditController from '@/actions/App/Http/Controllers/Jobs/JobEditController';
import JobPressAssignmentController from '@/actions/App/Http/Controllers/Jobs/JobPressAssignmentController';
import JobQuoteController from '@/actions/App/Http/Controllers/Jobs/JobQuoteController';
import WorkOrderController from '@/actions/App/Http/Controllers/Jobs/WorkOrderController';
import JobStageController from '@/actions/App/Http/Controllers/Jobs/JobStageController';
import JobStatusController from '@/actions/App/Http/Controllers/Jobs/JobStatusController';
import OdooInvoiceSyncController from '@/actions/App/Http/Controllers/Jobs/OdooInvoiceSyncController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { JobStatusBadge } from '@/components/jobs/status-badge';
import { PaymentForm } from '@/components/payments/payment-form';
import PressRoutingSelector from '@/components/routing/PressRoutingSelector';
import type {
    JobRoutingRequirements,
    Press,
} from '@/components/routing/PressRoutingSelector';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { date, egp, num } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { JobStatus, JobType, Option } from '@/types';

type Stage = {
    id: number;
    name: string;
    status: 'pending' | 'in_progress' | 'done';
    statusLabel: string;
    startedAt: string | null;
    completedAt: string | null;
};

type JobDetail = {
    id: number;
    name: string;
    type: JobType;
    typeLabel: string;
    status: JobStatus;
    statusLabel: string;
    statuses: Option[];
    next: { value: JobStatus; label: string; allowed: boolean } | null;
    editable: boolean;
    warnings: { type: 'credit' | 'price'; message: string }[];
    activity: {
        id: number;
        description: string;
        user: string;
        at: string | null;
    }[];
    payments: {
        items: {
            id: number;
            amount: number;
            method: string;
            paidAt: string;
            reference: string | null;
        }[];
        total: number;
        methods: Option[];
    };
    customer: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
    };
    createdAt: string | null;
    box: {
        boxType: string | null;
        shape: string | null;
        dimensions: string;
        paper: string | null;
        supplier: string | null;
        pricePerTon: number | null;
        printColors: number;
        lamination: string;
        die: string | null;
        dieLocation: string | null;
        newDie: boolean;
        rawSheetsNeeded: number | null;
        upsPerRawSheet: number | null;
        interlocked: boolean;
    } | null;
    quantity: number | null;
    producedQuantity: number | null;
    baseCost: number;
    marginPercent: number;
    finalPrice: number;
    unitPrice: number | null;
    costLines: { id: number; label: string; amount: number }[];
    paperItems: {
        id: number;
        label: string;
        paper: string;
        supplier: string | null;
        size: string;
        sheets: number;
        weightKg: number;
        cost: number;
    }[];
    stages: Stage[];
    routing: {
        requirements: JobRoutingRequirements;
        currentPressId: string | null;
        history: {
            id: number;
            press: string;
            by: string | null;
            at: string | null;
        }[];
    };
    odoo: {
        syncs: {
            id: number;
            status: 'pending' | 'sent' | 'success' | 'failed';
            statusLabel: string;
            invoiceId: string | null;
            invoiceName: string | null;
            billedTotal: number | null;
            manual: boolean;
            error: string | null;
            at: string | null;
        }[];
        invoiced: boolean;
        canInvoice: boolean;
    };
};

export default function JobShow({
    job,
    presses,
}: {
    job: JobDetail;
    presses: Press[];
}) {
    const can = useCan();
    const inProductionPhase =
        job.status === 'approved' || job.status === 'in_production';

    return (
        <PageBody>
            <Head title={`#${job.id} ${job.name}`} />
            <PageHeader
                title={`#${job.id} — ${job.name}`}
                description={`${job.customer.name} · ${job.typeLabel} · اتسجلت ${date(job.createdAt)}`}
                actions={
                    <>
                        {job.editable && (
                            <Button asChild variant="secondary">
                                <Link href={JobEditController.edit(job.id)}>
                                    <Pencil /> تعديل / إعادة تسعير
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="secondary">
                            <a
                                href={JobQuoteController.show.url(job.id)}
                                target="_blank"
                                rel="noopener"
                            >
                                <FileText /> عرض السعر (PDF)
                            </a>
                        </Button>
                        <Button asChild variant="secondary">
                            <a
                                href={WorkOrderController.show.url(job.id)}
                                target="_blank"
                                rel="noopener"
                            >
                                <QrCode /> أمر الشغل
                            </a>
                        </Button>
                        <JobStatusBadge
                            status={job.status}
                            label={job.statusLabel}
                        />
                    </>
                }
            />

            {job.warnings.map((w) => (
                <div
                    key={w.type}
                    className="mb-3 flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-300"
                >
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <span>{w.message}</span>
                    {w.type === 'price' && job.editable && (
                        <Link
                            href={JobEditController.edit(job.id)}
                            className="ms-auto shrink-0 underline"
                        >
                            إعادة تسعير
                        </Link>
                    )}
                </div>
            ))}

            <StatusStepper job={job} />

            <div className="mb-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Stat label="الكمية المطلوبة" value={num(job.quantity)} />
                <Stat
                    label="الكمية الفعلية"
                    value={num(job.producedQuantity)}
                />
                <Stat label="التكلفة" value={egp(job.baseCost)} />
                <Stat label="نسبة الربح" value={`${num(job.marginPercent)}%`} />
                <Stat
                    label="السعر النهائي"
                    value={egp(job.finalPrice)}
                    highlight
                />
                <Stat
                    label="سعر القطعة"
                    value={job.unitPrice !== null ? egp(job.unitPrice) : '—'}
                />
            </div>

            <div className="grid gap-6 *:min-w-0 lg:grid-cols-2">
                {job.box ? (
                    <BoxDetails box={job.box} />
                ) : (
                    <PaperItems items={job.paperItems} />
                )}
                <CostLines lines={job.costLines} total={job.baseCost} />
            </div>

            {inProductionPhase && (
                <div className="mt-6 grid gap-6 *:min-w-0 lg:grid-cols-2">
                    <Stages job={job} canEdit={can('run-production')} />
                    <div className="space-y-4">
                        {can('run-production') ? (
                            <Routing
                                job={job}
                                presses={presses}
                                canEditBacklog={can('update-press-backlog')}
                            />
                        ) : (
                            <AssignmentHistory history={job.routing.history} />
                        )}
                    </div>
                </div>
            )}

            {job.status === 'in_production' && can('run-production') && (
                <Completion job={job} />
            )}

            {(job.status === 'completed' ||
                job.status === 'invoiced' ||
                job.odoo.syncs.length > 0) && (
                <Invoicing job={job} canManage={can('manage-invoicing')} />
            )}

            {(job.payments.items.length > 0 || can('manage-invoicing')) && (
                <section className="mt-6">
                    <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-base font-semibold">
                            الدفعات على الشغلانة
                        </h2>
                        <span className="text-sm text-muted-foreground">
                            اتدفع {egp(job.payments.total)} من{' '}
                            {egp(job.finalPrice)} (قبل الضريبة) ·{' '}
                            <Link
                                href={CustomerController.show(job.customer.id)}
                                className="text-emerald-400 hover:underline"
                            >
                                حساب العميل
                            </Link>
                        </span>
                    </div>
                    {job.payments.items.length > 0 && (
                        <ul className="mb-3 space-y-1 text-sm">
                            {job.payments.items.map((p) => (
                                <li key={p.id}>
                                    <span dir="ltr">{p.paidAt}</span> —{' '}
                                    {egp(p.amount)} ({p.method})
                                    {p.reference && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {p.reference}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    {can('manage-invoicing') && (
                        <PaymentForm
                            customerId={job.customer.id}
                            methods={job.payments.methods}
                            jobId={job.id}
                        />
                    )}
                </section>
            )}

            {job.activity.length > 0 && (
                <section className="mt-6">
                    <h2 className="mb-3 text-base font-semibold">
                        سجل الشغلانة
                    </h2>
                    <ol className="space-y-2 border-s border-border ps-4">
                        {job.activity.map((a) => (
                            <li key={a.id} className="text-sm">
                                <span className="text-muted-foreground">
                                    {date(a.at)} · {a.user}:
                                </span>{' '}
                                {a.description}
                            </li>
                        ))}
                    </ol>
                </section>
            )}
        </PageBody>
    );
}

function Stat({
    label,
    value,
    highlight = false,
}: {
    label: string;
    value: string;
    highlight?: boolean;
}) {
    return (
        <div className="rounded-xl border border-border bg-card px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-lg font-semibold',
                    highlight && 'text-emerald-400',
                )}
            >
                {value}
            </div>
        </div>
    );
}

function StatusStepper({ job }: { job: JobDetail }) {
    const currentIndex = job.statuses.findIndex((s) => s.value === job.status);
    // Completion needs the produced quantity (its own form); invoicing is Odoo's job.
    const showNextButton = job.next && job.next.value !== 'completed';

    return (
        <Card className="mb-6 gap-4 py-4">
            <CardContent className="flex flex-wrap items-center justify-between gap-4 px-4">
                <ol className="flex flex-wrap items-center gap-2 text-sm">
                    {job.statuses.map((status, index) => (
                        <li
                            key={status.value}
                            className="flex items-center gap-2"
                        >
                            <span
                                className={cn(
                                    'flex items-center gap-1.5 rounded-full px-3 py-1',
                                    index < currentIndex &&
                                        'text-emerald-400/80',
                                    index === currentIndex &&
                                        'bg-emerald-500 font-medium text-slate-950',
                                    index > currentIndex &&
                                        'text-muted-foreground',
                                )}
                            >
                                {index < currentIndex && (
                                    <Check className="size-3.5" />
                                )}
                                {status.label}
                            </span>
                            {index < job.statuses.length - 1 && (
                                <ArrowLeft className="size-3.5 text-muted-foreground" />
                            )}
                        </li>
                    ))}
                </ol>
                {showNextButton && job.next && (
                    <Button
                        disabled={!job.next.allowed}
                        title={
                            job.next.allowed
                                ? undefined
                                : 'صلاحيتك متسمحش بالخطوة دي'
                        }
                        onClick={() => {
                            if (
                                job.next &&
                                window.confirm(
                                    `تنقل الشغلانة لـ «${job.next.label}»؟`,
                                )
                            ) {
                                router.patch(
                                    JobStatusController.update.url(job.id),
                                    { status: job.next.value },
                                    { preserveScroll: true },
                                );
                            }
                        }}
                    >
                        انقلها لـ «{job.next.label}»
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 py-1.5 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-left font-medium">{children}</span>
        </div>
    );
}

function BoxDetails({ box }: { box: NonNullable<JobDetail['box']> }) {
    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle className="text-base">مواصفات العلبة</CardTitle>
            </CardHeader>
            <CardContent className="divide-y divide-border/60 px-4">
                <Row label="النوع / الشكل">
                    {box.boxType} — {box.shape}
                </Row>
                <Row label="المقاس (سم)">
                    <span dir="ltr">{box.dimensions}</span>
                </Row>
                <Row label="الورق">{box.paper ?? '—'}</Row>
                <Row label="المورد">
                    {box.supplier ?? '—'}
                    {box.pricePerTon !== null &&
                        ` (${egp(box.pricePerTon)} / طن)`}
                </Row>
                <Row label="الطباعة">
                    {box.printColors === 0 ? 'سادة' : `${box.printColors} لون`}
                </Row>
                <Row label="السلوفان">{box.lamination}</Row>
                <Row label="الاسطمبة">
                    {box.newDie ? (
                        <Badge className="border-transparent bg-red-500/15 text-red-300">
                            اسطمبة جديدة
                        </Badge>
                    ) : (
                        (box.die ?? '—')
                    )}
                    {box.dieLocation && (
                        <span className="text-xs text-muted-foreground">
                            {' '}
                            · {box.dieLocation}
                        </span>
                    )}
                </Row>
                <Row label="الإعداد المبدئي">
                    {num(box.rawSheetsNeeded)} فرخ · {num(box.upsPerRawSheet)}{' '}
                    علبة/فرخ خام
                    {box.interlocked && ' · مونتاج متداخل'}
                </Row>
            </CardContent>
        </Card>
    );
}

function PaperItems({ items }: { items: JobDetail['paperItems'] }) {
    return (
        <div>
            <h2 className="mb-3 text-base font-semibold">بنود الورق</h2>
            <DataTable>
                <thead>
                    <tr>
                        <Th>البند</Th>
                        <Th>الورق</Th>
                        <Th>المقاس</Th>
                        <Th>الأفرخ</Th>
                        <Th>الوزن</Th>
                        <Th>التكلفة</Th>
                    </tr>
                </thead>
                <tbody>
                    {items.length === 0 && (
                        <EmptyRow colSpan={6}>مفيش بنود ورق</EmptyRow>
                    )}
                    {items.map((item) => (
                        <tr key={item.id}>
                            <Td className="font-medium">{item.label}</Td>
                            <Td>
                                {item.paper}
                                {item.supplier && (
                                    <div className="text-xs text-muted-foreground">
                                        {item.supplier}
                                    </div>
                                )}
                            </Td>
                            <Td dir="ltr" className="text-right">
                                {item.size}
                            </Td>
                            <Td>{num(item.sheets)}</Td>
                            <Td>{num(item.weightKg)} كجم</Td>
                            <Td>{egp(item.cost)}</Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </div>
    );
}

function CostLines({
    lines,
    total,
}: {
    lines: JobDetail['costLines'];
    total: number;
}) {
    return (
        <div>
            <h2 className="mb-3 text-base font-semibold">بنود التكلفة</h2>
            <DataTable>
                <tbody>
                    {lines.length === 0 && (
                        <EmptyRow colSpan={2}>مفيش بنود</EmptyRow>
                    )}
                    {lines.map((line) => (
                        <tr key={line.id}>
                            <Td>{line.label}</Td>
                            <Td className="text-left">{egp(line.amount)}</Td>
                        </tr>
                    ))}
                    <tr>
                        <Td className="font-semibold">الإجمالي</Td>
                        <Td className="text-left font-semibold">
                            {egp(total)}
                        </Td>
                    </tr>
                </tbody>
            </DataTable>
        </div>
    );
}

const nextStageStatus: Record<Stage['status'], Stage['status']> = {
    pending: 'in_progress',
    in_progress: 'done',
    done: 'pending',
};

const stageAction: Record<Stage['status'], string> = {
    pending: 'ابدأ',
    in_progress: 'خلصت',
    done: 'رجّعها',
};

function Stages({ job, canEdit }: { job: JobDetail; canEdit: boolean }) {
    const doneCount = job.stages.filter((s) => s.status === 'done').length;

    return (
        <Card className="gap-3 py-4">
            <CardHeader className="flex flex-row items-center justify-between px-4">
                <CardTitle className="text-base">مراحل الإنتاج</CardTitle>
                <span className="text-sm text-muted-foreground" dir="ltr">
                    {doneCount} / {job.stages.length}
                </span>
            </CardHeader>
            <CardContent className="space-y-2 px-4">
                {job.stages.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        المراحل بتتعمل لما العميل يوافق.
                    </p>
                )}
                {job.stages.map((stage) => (
                    <div
                        key={stage.id}
                        className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
                    >
                        <div className="flex items-center gap-2">
                            {stage.status === 'done' && (
                                <Check className="size-4 text-emerald-400" />
                            )}
                            {stage.status === 'in_progress' && (
                                <Loader2 className="size-4 animate-spin text-amber-400" />
                            )}
                            {stage.status === 'pending' && (
                                <CircleDashed className="size-4 text-muted-foreground" />
                            )}
                            <span
                                className={cn(
                                    'text-sm',
                                    stage.status === 'done' &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {stage.name}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">
                                {stage.completedAt
                                    ? date(stage.completedAt)
                                    : stage.startedAt
                                      ? `بدأت ${date(stage.startedAt)}`
                                      : ''}
                            </span>
                            {canEdit && (
                                <Button
                                    size="sm"
                                    variant={
                                        stage.status === 'in_progress'
                                            ? 'default'
                                            : stage.status === 'done'
                                              ? 'ghost'
                                              : 'secondary'
                                    }
                                    className={
                                        stage.status === 'done'
                                            ? 'text-muted-foreground'
                                            : undefined
                                    }
                                    onClick={() =>
                                        router.patch(
                                            JobStageController.update.url({
                                                job: job.id,
                                                stage: stage.id,
                                            }),
                                            {
                                                status: nextStageStatus[
                                                    stage.status
                                                ],
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {stageAction[stage.status]}
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function Routing({
    job,
    presses,
    canEditBacklog,
}: {
    job: JobDetail;
    presses: Press[];
    canEditBacklog: boolean;
}) {
    const timers = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
    // Stable identity so the selector doesn't re-filter on every render.
    const requirements = useMemo(
        () => job.routing.requirements,
        [job.routing.requirements],
    );

    const saveBacklog = (pressId: string, days: number) => {
        clearTimeout(timers.current[pressId]);
        timers.current[pressId] = setTimeout(() => {
            router.patch(
                PressController.updateBacklog.url(Number(pressId)),
                { current_backlog_days: days },
                { preserveScroll: true, preserveState: true, only: [] },
            );
        }, 700);
    };

    return (
        <>
            {job.status === 'in_production' && !job.routing.currentPressId && (
                <div className="flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-300">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    الشغلانة في الإنتاج ولسه متوزعتش على مطبعة.
                </div>
            )}
            <PressRoutingSelector
                key={job.routing.currentPressId ?? 'none'}
                presses={presses}
                requirements={requirements}
                initialSelectedPressId={job.routing.currentPressId}
                onSelectPress={(press) => {
                    if (
                        press.id !== job.routing.currentPressId &&
                        window.confirm(`توزع الشغلانة على «${press.name}»؟`)
                    ) {
                        router.post(
                            JobPressAssignmentController.store.url(job.id),
                            { press_id: Number(press.id) },
                            { preserveScroll: true },
                        );
                    }
                }}
                onBacklogChange={canEditBacklog ? saveBacklog : undefined}
            />
            <AssignmentHistory history={job.routing.history} />
        </>
    );
}

function AssignmentHistory({
    history,
}: {
    history: JobDetail['routing']['history'];
}) {
    if (history.length === 0) {
        return null;
    }

    return (
        <Card className="gap-2 py-4">
            <CardHeader className="px-4">
                <CardTitle className="text-sm">سجل التوزيع</CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 px-4 text-sm">
                {history.map((entry, index) => (
                    <div
                        key={entry.id}
                        className={cn(
                            'flex justify-between gap-2',
                            index > 0 && 'text-muted-foreground',
                        )}
                    >
                        <span>{entry.press}</span>
                        <span className="text-xs">
                            {entry.by ?? '—'} · {date(entry.at)}
                        </span>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function Completion({ job }: { job: JobDetail }) {
    return (
        <Card className="mt-6 gap-3 border-emerald-500/30 py-4">
            <CardHeader className="px-4">
                <CardTitle className="text-base">
                    تأكيد الكمية النهائية
                </CardTitle>
            </CardHeader>
            <CardContent className="px-4">
                <p className="mb-3 text-sm text-muted-foreground">
                    اكتب الكمية الفعلية بعد الهالك — دي اللي هتتعمل بيها فاتورة
                    أودو، مش الكمية المطلوبة.
                </p>
                <Form
                    {...JobCompletionController.store.form(job.id)}
                    options={{ preserveScroll: true }}
                    onBefore={() =>
                        window.confirm('تأكيد الكمية وإنهاء الشغلانة؟')
                    }
                    className="flex flex-wrap items-start gap-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <Input
                                    name="produced_quantity"
                                    type="number"
                                    min={1}
                                    defaultValue={job.quantity ?? ''}
                                    className="w-48"
                                    required
                                />
                                <InputError
                                    message={errors.produced_quantity}
                                />
                            </div>
                            <Button disabled={processing}>
                                تأكيد وإنهاء الشغلانة
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

const syncClass: Record<JobDetail['odoo']['syncs'][number]['status'], string> =
    {
        pending: 'bg-slate-500/15 text-slate-300',
        sent: 'bg-sky-500/15 text-sky-300',
        success: 'bg-emerald-500/15 text-emerald-300',
        failed: 'bg-red-500/15 text-red-300',
    };

function Invoicing({ job, canManage }: { job: JobDetail; canManage: boolean }) {
    const lastFailed = job.odoo.syncs[0]?.status === 'failed';

    return (
        <div className="mt-6 space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold">فاتورة أودو</h2>
                {canManage && job.odoo.canInvoice && (
                    <Button
                        variant="secondary"
                        onClick={() =>
                            router.post(
                                OdooInvoiceSyncController.store.url(job.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RefreshCw />{' '}
                        {job.odoo.syncs.length > 0
                            ? 'إعادة المحاولة'
                            : 'اعمل الفاتورة'}
                    </Button>
                )}
            </div>

            {job.odoo.canInvoice && job.odoo.syncs.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    الفاتورة بتتبعت لأودو تلقائي بعد تأكيد الكمية. لو السيرفر
                    بيشغّل الـ queue، هتظهر هنا خلال دقيقة.
                </p>
            )}

            <DataTable>
                <thead>
                    <tr>
                        <Th>المحاولة</Th>
                        <Th>الحالة</Th>
                        <Th>رقم الفاتورة</Th>
                        <Th>المبلغ</Th>
                        <Th>الخطأ</Th>
                        <Th>الوقت</Th>
                    </tr>
                </thead>
                <tbody>
                    {job.odoo.syncs.length === 0 && (
                        <EmptyRow colSpan={6}>مفيش محاولات لسه</EmptyRow>
                    )}
                    {job.odoo.syncs.map((sync) => (
                        <tr key={sync.id}>
                            <Td className="text-muted-foreground">
                                #{sync.id}
                            </Td>
                            <Td>
                                <Badge
                                    className={cn(
                                        'border-transparent',
                                        syncClass[sync.status],
                                    )}
                                >
                                    {sync.statusLabel}
                                </Badge>
                                {sync.manual && (
                                    <span className="ms-2 text-xs text-muted-foreground">
                                        (يدوي)
                                    </span>
                                )}
                            </Td>
                            <Td dir="ltr" className="text-right">
                                {sync.invoiceName ?? sync.invoiceId ?? '—'}
                            </Td>
                            <Td>
                                {sync.billedTotal !== null
                                    ? egp(sync.billedTotal)
                                    : '—'}
                            </Td>
                            <Td className="max-w-sm text-xs text-red-300">
                                {sync.error ?? ''}
                            </Td>
                            <Td className="text-xs">{date(sync.at)}</Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>

            {canManage && job.odoo.canInvoice && (
                <Card
                    className={cn(
                        'gap-3 py-4',
                        lastFailed && 'border-amber-500/40',
                    )}
                >
                    <CardHeader className="px-4">
                        <CardTitle className="text-sm">بديل يدوي</CardTitle>
                    </CardHeader>
                    <CardContent className="px-4">
                        <p className="mb-3 text-sm text-muted-foreground">
                            لو أودو مش راضي يستقبل الفاتورة، اعملها بإيدك في
                            أودو وسجّل رقمها هنا — الشغلانة هتتقفل كـ «اتعملت
                            فاتورة».
                        </p>
                        <Form
                            {...OdooInvoiceSyncController.manual.form(job.id)}
                            options={{ preserveScroll: true }}
                            className="flex flex-wrap items-start gap-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div>
                                        <Input
                                            name="odoo_invoice_id"
                                            dir="ltr"
                                            placeholder="INV/2026/00042"
                                            className="w-56"
                                        />
                                        <InputError
                                            message={errors.odoo_invoice_id}
                                        />
                                    </div>
                                    <Button
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        سجّل الفاتورة
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

JobShow.layout = {
    breadcrumbs: [{ title: 'الشغلانات', href: JobController.index() }],
};
