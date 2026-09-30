import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Catalog/CustomerController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { JobStatusBadge } from '@/components/jobs/status-badge';
import { PaymentForm } from '@/components/payments/payment-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { egp } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { JobStatus, Option } from '@/types';

type Props = {
    customer: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        credit_limit_egp: string | null;
        notes: string | null;
    };
    balance: { invoiced: number; paid: number; balance: number };
    jobs: {
        id: number;
        name: string;
        status: JobStatus;
        statusLabel: string;
        finalPrice: number;
        paid: number;
    }[];
    payments: {
        id: number;
        amount: number;
        method: string;
        paidAt: string;
        reference: string | null;
        notes: string | null;
        job: { id: number; name: string } | null;
        by: string | null;
    }[];
    methods: Option[];
};

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone?: 'danger' | 'good';
}) {
    return (
        <div className="rounded-xl border border-border bg-card px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-2xl font-semibold tabular-nums',
                    tone === 'danger' && 'text-red-400',
                    tone === 'good' && 'text-emerald-400',
                )}
            >
                {value}
            </div>
        </div>
    );
}

export default function CustomerShow({
    customer,
    balance,
    jobs,
    payments,
    methods,
}: Props) {
    const can = useCan();
    const limit = customer.credit_limit_egp
        ? Number(customer.credit_limit_egp)
        : null;

    return (
        <PageBody>
            <Head title={customer.name} />
            <PageHeader
                title={customer.name}
                description={
                    [customer.phone, customer.email]
                        .filter(Boolean)
                        .join(' · ') || undefined
                }
                actions={
                    can('manage-customers') && (
                        <Button asChild variant="secondary">
                            <Link href={CustomerController.edit(customer.id)}>
                                <Pencil /> تعديل
                            </Link>
                        </Button>
                    )
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <Stat
                    label="اتفوتر (شامل الضريبة)"
                    value={egp(balance.invoiced)}
                />
                <Stat label="اتدفع" value={egp(balance.paid)} />
                <Stat
                    label={
                        balance.balance >= 0 ? 'المستحق عليه' : 'رصيد دائن له'
                    }
                    value={egp(Math.abs(balance.balance))}
                    tone={balance.balance > 0 ? 'danger' : 'good'}
                />
                <Stat
                    label="حد الائتمان"
                    value={limit !== null ? egp(limit) : 'مفيش'}
                />
            </div>

            {can('manage-invoicing') && (
                <Card className="mb-6 gap-3 py-4">
                    <CardHeader className="px-4">
                        <CardTitle className="text-base">تسجيل دفعة</CardTitle>
                    </CardHeader>
                    <CardContent className="px-4">
                        <PaymentForm
                            customerId={customer.id}
                            methods={methods}
                            jobs={jobs.map((j) => ({ id: j.id, name: j.name }))}
                        />
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-6 *:min-w-0 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 text-base font-semibold">الشغلانات</h2>
                    <DataTable>
                        <thead>
                            <tr>
                                <Th>الشغلانة</Th>
                                <Th>السعر</Th>
                                <Th>اتدفع عليها</Th>
                                <Th>الحالة</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {jobs.length === 0 && (
                                <EmptyRow colSpan={4}>مفيش شغلانات</EmptyRow>
                            )}
                            {jobs.map((j) => (
                                <tr key={j.id}>
                                    <Td>
                                        <Link
                                            href={JobController.show(j.id)}
                                            className="font-medium hover:text-emerald-400"
                                        >
                                            #{j.id} {j.name}
                                        </Link>
                                    </Td>
                                    <Td>{egp(j.finalPrice)}</Td>
                                    <Td>{j.paid > 0 ? egp(j.paid) : '—'}</Td>
                                    <Td>
                                        <JobStatusBadge
                                            status={j.status}
                                            label={j.statusLabel}
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </section>

                <section>
                    <h2 className="mb-3 text-base font-semibold">الدفعات</h2>
                    <DataTable>
                        <thead>
                            <tr>
                                <Th>التاريخ</Th>
                                <Th>المبلغ</Th>
                                <Th>الطريقة</Th>
                                <Th>على</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {payments.length === 0 && (
                                <EmptyRow colSpan={5}>
                                    مفيش دفعات مسجلة
                                </EmptyRow>
                            )}
                            {payments.map((p) => (
                                <tr key={p.id}>
                                    <Td dir="ltr" className="text-right">
                                        {p.paidAt}
                                    </Td>
                                    <Td className="font-medium">
                                        {egp(p.amount)}
                                    </Td>
                                    <Td>
                                        {p.method}
                                        {p.reference && (
                                            <div
                                                className="text-xs text-muted-foreground"
                                                dir="ltr"
                                            >
                                                {p.reference}
                                            </div>
                                        )}
                                    </Td>
                                    <Td>
                                        {p.job ? (
                                            <Link
                                                href={JobController.show(
                                                    p.job.id,
                                                )}
                                                className="hover:text-emerald-400"
                                            >
                                                #{p.job.id}
                                            </Link>
                                        ) : (
                                            'على الحساب'
                                        )}
                                    </Td>
                                    <Td className="text-left">
                                        {can('manage-invoicing') && (
                                            <DeleteButton
                                                url={
                                                    PaymentController.destroy(
                                                        p.id,
                                                    ).url
                                                }
                                                confirmText="مسح الدفعة دي؟"
                                            />
                                        )}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </section>
            </div>

            {customer.notes && (
                <p className="mt-6 text-sm whitespace-pre-line text-muted-foreground">
                    {customer.notes}
                </p>
            )}
        </PageBody>
    );
}

CustomerShow.layout = {
    breadcrumbs: [{ title: 'العملاء', href: CustomerController.index() }],
};
