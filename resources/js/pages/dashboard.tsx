import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Box, FileText, Users } from 'lucide-react';
import CuttingDieController from '@/actions/App/Http/Controllers/Catalog/CuttingDieController';
import BoxJobController from '@/actions/App/Http/Controllers/Jobs/BoxJobController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import ManualJobController from '@/actions/App/Http/Controllers/Jobs/ManualJobController';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { JobStatusBadge } from '@/components/jobs/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { egp } from '@/lib/format';
import { dashboard } from '@/routes';
import type { JobStatus } from '@/types';

export default function Dashboard({
    statusCounts,
    openLeads,
    failedInvoices,
    diesNeedingAttention,
    recentJobs,
}: {
    statusCounts: { value: JobStatus; label: string; count: number }[];
    openLeads: number;
    failedInvoices: number;
    diesNeedingAttention: number;
    recentJobs: {
        id: number;
        name: string;
        customer: string;
        status: JobStatus;
        statusLabel: string;
        finalPrice: number;
    }[];
}) {
    const can = useCan();

    return (
        <PageBody>
            <Head title="الرئيسية" />
            <PageHeader
                title="الرئيسية"
                actions={
                    can('create-jobs') && (
                        <>
                            <Button asChild>
                                <Link href={BoxJobController.create()}>
                                    <Box /> تسعير علبة
                                </Link>
                            </Button>
                            <Button asChild variant="secondary">
                                <Link href={ManualJobController.create()}>
                                    <FileText /> حساب يدوي
                                </Link>
                            </Button>
                        </>
                    )
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                {statusCounts.map((status) => (
                    <Link
                        key={status.value}
                        href={JobController.index({
                            query: { status: status.value },
                        })}
                        className="rounded-xl border border-border bg-card px-4 py-3 transition-colors hover:border-emerald-500/40"
                    >
                        <div className="text-xs text-muted-foreground">
                            {status.label}
                        </div>
                        <div className="mt-1 text-2xl font-semibold">
                            {status.count}
                        </div>
                    </Link>
                ))}
            </div>

            {(failedInvoices > 0 || diesNeedingAttention > 0) && (
                <div className="mb-6 space-y-2">
                    {failedInvoices > 0 && (
                        <Link
                            href={JobController.index({
                                query: { status: 'completed' },
                            })}
                            className="flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300"
                        >
                            <AlertTriangle className="size-4" />
                            {failedInvoices} شغلانة فاتورتها فشلت في أودو —
                            افتحها واعمل إعادة محاولة أو سجّل الفاتورة يدوي
                        </Link>
                    )}
                    {diesNeedingAttention > 0 && (
                        <Link
                            href={CuttingDieController.index()}
                            className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-300"
                        >
                            <AlertTriangle className="size-4" />
                            {diesNeedingAttention} اسطمبة محتاجة كاوتش أو صيانة
                        </Link>
                    )}
                </div>
            )}

            <div className="grid gap-6 *:min-w-0 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-semibold">
                            آخر الشغلانات
                        </h2>
                        <Link
                            href={JobController.index()}
                            className="text-sm text-emerald-400 hover:underline"
                        >
                            كل الشغلانات
                        </Link>
                    </div>
                    <DataTable>
                        <thead>
                            <tr>
                                <Th>#</Th>
                                <Th>الشغلانة</Th>
                                <Th>العميل</Th>
                                <Th>السعر</Th>
                                <Th>الحالة</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {recentJobs.length === 0 && (
                                <EmptyRow colSpan={5}>مفيش شغلانات لسه</EmptyRow>
                            )}
                            {recentJobs.map((job) => (
                                <tr key={job.id}>
                                    <Td className="text-muted-foreground">
                                        {job.id}
                                    </Td>
                                    <Td>
                                        <Link
                                            href={JobController.show(job.id)}
                                            className="font-medium hover:text-emerald-400"
                                        >
                                            {job.name}
                                        </Link>
                                    </Td>
                                    <Td>{job.customer}</Td>
                                    <Td>{egp(job.finalPrice)}</Td>
                                    <Td>
                                        <JobStatusBadge
                                            status={job.status}
                                            label={job.statusLabel}
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </div>

                {can('manage-leads') && (
                    <Link
                        href={LeadController.index()}
                        className="flex h-fit items-center gap-4 rounded-xl border border-border bg-card p-5 transition-colors hover:border-emerald-500/40"
                    >
                        <Users className="size-8 text-emerald-400" />
                        <div>
                            <div className="text-2xl font-semibold">
                                {openLeads}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                فرصة مفتوحة محتاجة متابعة
                            </div>
                        </div>
                    </Link>
                )}
            </div>
        </PageBody>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'الرئيسية', href: dashboard() }],
};
