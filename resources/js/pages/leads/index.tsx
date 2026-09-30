import { Head, Link, router } from '@inertiajs/react';
import { Box, FileText, Pencil, Plus } from 'lucide-react';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Pagination } from '@/components/crud/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { num } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

type LeadRow = {
    id: number;
    contactName: string;
    companyName: string | null;
    phone: string | null;
    source: string | null;
    status: 'new' | 'contacted' | 'quoted' | 'won' | 'lost';
    statusLabel: string;
    expectedQuantity: number | null;
    customer: string | null;
    owner: string | null;
    convertedJobId: number | null;
};

const statusClass: Record<LeadRow['status'], string> = {
    new: 'bg-sky-500/15 text-sky-300',
    contacted: 'bg-indigo-500/15 text-indigo-300',
    quoted: 'bg-amber-500/15 text-amber-300',
    won: 'bg-emerald-500/15 text-emerald-300',
    lost: 'bg-slate-500/15 text-slate-400',
};

export default function LeadsIndex({
    leads,
    statuses,
    filters,
}: {
    leads: Paginated<LeadRow>;
    statuses: Option[];
    filters: { status: string };
}) {
    const convert = (lead: LeadRow, type: 'box' | 'manual') =>
        router.post(LeadController.convert.url(lead.id), { type });

    return (
        <PageBody>
            <Head title="الفرص" />
            <PageHeader
                title="الفرص"
                description="العملاء المحتملين — حوّل الفرصة لشغلانة من هنا وهي هتتربط بيها تلقائي"
                actions={
                    <Button asChild>
                        <Link href={LeadController.create()}>
                            <Plus /> فرصة جديدة
                        </Link>
                    </Button>
                }
            />

            <div className="mb-4 flex flex-wrap gap-2">
                {[{ value: '', label: 'الكل' }, ...statuses].map((status) => (
                    <Button
                        key={status.value}
                        asChild
                        size="sm"
                        variant={
                            filters.status === status.value
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        <Link
                            href={LeadController.index({
                                query: status.value
                                    ? { status: status.value }
                                    : {},
                            })}
                        >
                            {status.label}
                        </Link>
                    </Button>
                ))}
            </div>

            <DataTable>
                <thead>
                    <tr>
                        <Th>جهة الاتصال</Th>
                        <Th>الشركة / العميل</Th>
                        <Th>التليفون</Th>
                        <Th>المصدر</Th>
                        <Th>الكمية المتوقعة</Th>
                        <Th>الحالة</Th>
                        <Th>المسؤول</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {leads.data.length === 0 && (
                        <EmptyRow colSpan={8}>
                            <div className="space-y-3">
                                <p>مفيش فرص بالحالة دي.</p>
                                <Button asChild size="sm">
                                    <Link href={LeadController.create()}>
                                        <Plus /> فرصة جديدة
                                    </Link>
                                </Button>
                            </div>
                        </EmptyRow>
                    )}
                    {leads.data.map((lead) => (
                        <tr key={lead.id}>
                            <Td className="font-medium">{lead.contactName}</Td>
                            <Td>{lead.customer ?? lead.companyName ?? '—'}</Td>
                            <Td dir="ltr" className="text-right">
                                {lead.phone ?? '—'}
                            </Td>
                            <Td>{lead.source ?? '—'}</Td>
                            <Td>{num(lead.expectedQuantity)}</Td>
                            <Td>
                                <Badge
                                    className={cn(
                                        'border-transparent',
                                        statusClass[lead.status],
                                    )}
                                >
                                    {lead.statusLabel}
                                </Badge>
                            </Td>
                            <Td>{lead.owner ?? '—'}</Td>
                            <Td className="text-left whitespace-nowrap">
                                {lead.convertedJobId ? (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link
                                            href={JobController.show(
                                                lead.convertedJobId,
                                            )}
                                        >
                                            الشغلانة #{lead.convertedJobId}
                                        </Link>
                                    </Button>
                                ) : (
                                    lead.status !== 'lost' && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    convert(lead, 'box')
                                                }
                                                title="حوّل لتسعير علبة"
                                            >
                                                <Box className="size-4" /> علبة
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    convert(lead, 'manual')
                                                }
                                                title="حوّل لحساب يدوي"
                                            >
                                                <FileText className="size-4" />{' '}
                                                يدوي
                                            </Button>
                                        </>
                                    )
                                )}
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={LeadController.edit(lead.id)}>
                                        <Pencil className="size-4" />
                                    </Link>
                                </Button>
                                <DeleteButton
                                    url={LeadController.destroy(lead.id).url}
                                />
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
            <Pagination page={leads} />
        </PageBody>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'الفرص', href: LeadController.index() }],
};
