import { Form, Head, Link } from '@inertiajs/react';
import { Box, FileText, Search } from 'lucide-react';
import BoxJobController from '@/actions/App/Http/Controllers/Jobs/BoxJobController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import ManualJobController from '@/actions/App/Http/Controllers/Jobs/ManualJobController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { NativeSelect } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Pagination } from '@/components/crud/pagination';
import { JobStatusBadge } from '@/components/jobs/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { egp, num } from '@/lib/format';
import type { JobStatus, JobType, Option, Paginated } from '@/types';

type JobRow = {
    id: number;
    name: string;
    customer: string;
    type: JobType;
    typeLabel: string;
    quantity: number | null;
    producedQuantity: number | null;
    finalPrice: number;
    status: JobStatus;
    statusLabel: string;
};

export default function JobsIndex({
    jobs,
    filters,
    statuses,
    types,
    customers,
}: {
    jobs: Paginated<JobRow>;
    filters: {
        status: string;
        type: string;
        customer: string | number;
        search: string;
    };
    statuses: Option[];
    types: Option[];
    customers: { id: number; name: string }[];
}) {
    const can = useCan();

    return (
        <PageBody>
            <Head title="الشغلانات" />
            <PageHeader
                title="الشغلانات"
                description="كل عروض الأسعار والشغل في الإنتاج — من المسودة لحد الفاتورة"
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

            <Form
                {...JobController.index.form()}
                className="mb-4 flex flex-wrap items-center gap-2"
            >
                <Input
                    name="search"
                    defaultValue={filters.search}
                    placeholder="رقم الشغلانة أو الاسم أو العميل"
                    className="w-64"
                />
                <NativeSelect
                    name="status"
                    options={statuses}
                    placeholder="كل الحالات"
                    defaultValue={filters.status}
                    className="w-40"
                />
                <NativeSelect
                    name="type"
                    options={types}
                    placeholder="كل الأنواع"
                    defaultValue={filters.type}
                    className="w-40"
                />
                <NativeSelect
                    name="customer"
                    options={customers.map((c) => ({
                        value: String(c.id),
                        label: c.name,
                    }))}
                    placeholder="كل العملاء"
                    defaultValue={String(filters.customer ?? '')}
                    className="w-48"
                />
                <Button type="submit" variant="secondary">
                    <Search /> فلترة
                </Button>
            </Form>

            <DataTable>
                <thead>
                    <tr>
                        <Th>#</Th>
                        <Th>الشغلانة</Th>
                        <Th>العميل</Th>
                        <Th>النوع</Th>
                        <Th>الكمية</Th>
                        <Th>السعر النهائي</Th>
                        <Th>الحالة</Th>
                    </tr>
                </thead>
                <tbody>
                    {jobs.data.length === 0 && (
                        <EmptyRow colSpan={7}>
                            <div className="space-y-3">
                                <p>مفيش شغلانات بالفلتر ده.</p>
                                {can('create-jobs') && (
                                    <Button asChild size="sm">
                                        <Link href={BoxJobController.create()}>
                                            <Box /> سعّر علبة جديدة
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </EmptyRow>
                    )}
                    {jobs.data.map((job) => (
                        <tr key={job.id} className="hover:bg-accent/40">
                            <Td className="text-muted-foreground">{job.id}</Td>
                            <Td>
                                <Link
                                    href={JobController.show(job.id)}
                                    className="font-medium hover:text-emerald-400"
                                >
                                    {job.name}
                                </Link>
                            </Td>
                            <Td>{job.customer}</Td>
                            <Td>{job.typeLabel}</Td>
                            <Td>
                                {num(job.quantity)}
                                {job.producedQuantity !== null && (
                                    <span className="text-xs text-muted-foreground">
                                        {' '}
                                        (فعلي {num(job.producedQuantity)})
                                    </span>
                                )}
                            </Td>
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
            <Pagination page={jobs} />
        </PageBody>
    );
}

JobsIndex.layout = {
    breadcrumbs: [{ title: 'الشغلانات', href: JobController.index() }],
};
