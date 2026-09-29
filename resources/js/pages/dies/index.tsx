import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import CuttingDieController from '@/actions/App/Http/Controllers/Catalog/CuttingDieController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';

type DieRow = {
    id: number;
    code: string;
    name: string;
    dimensions: string;
    closure: string;
    rack_location: string | null;
    ups_on_cut_sheet: number;
    cut_fraction: string;
    condition: 'ready' | 'needs_rubber' | 'maintenance';
    conditionLabel: string;
    jobs_run_count: number;
    estimated_lifespan_jobs: number | null;
};

const conditionClass: Record<DieRow['condition'], string> = {
    ready: 'bg-emerald-500/15 text-emerald-400',
    needs_rubber: 'bg-amber-500/15 text-amber-400',
    maintenance: 'bg-red-500/15 text-red-400',
};

export default function DiesIndex({ dies }: { dies: DieRow[] }) {
    const can = useCan();

    return (
        <PageBody>
            <Head title="الاسطمبات" />
            <PageHeader
                title="مخزن الاسطمبات"
                description="الحاسبة بتدور هنا على اسطمبة مقاسها قريب من العلبة المطلوبة"
                actions={
                    can('manage-catalog') && (
                        <Button asChild>
                            <Link href={CuttingDieController.create()}>
                                <Plus /> اسطمبة جديدة
                            </Link>
                        </Button>
                    )
                }
            />
            <DataTable>
                <thead>
                    <tr>
                        <Th>الكود</Th>
                        <Th>الاسم</Th>
                        <Th>المقاس (ط×ع×ارتفاع سم)</Th>
                        <Th>القفل</Th>
                        <Th>العلب في الشابلونة</Th>
                        <Th>الفرخ</Th>
                        <Th>المكان</Th>
                        <Th>الحالة</Th>
                        <Th>عدد مرات التشغيل</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {dies.length === 0 && (
                        <EmptyRow colSpan={10}>مفيش اسطمبات لسه</EmptyRow>
                    )}
                    {dies.map((die) => (
                        <tr key={die.id}>
                            <Td dir="ltr" className="text-right font-mono">
                                {die.code}
                            </Td>
                            <Td className="font-medium">{die.name}</Td>
                            <Td dir="ltr" className="text-right">
                                {die.dimensions}
                            </Td>
                            <Td>{die.closure}</Td>
                            <Td>{die.ups_on_cut_sheet}</Td>
                            <Td dir="ltr" className="text-right">
                                {die.cut_fraction}
                            </Td>
                            <Td>{die.rack_location ?? '—'}</Td>
                            <Td>
                                <Badge
                                    className={cn(
                                        'border-transparent',
                                        conditionClass[die.condition],
                                    )}
                                >
                                    {die.conditionLabel}
                                </Badge>
                            </Td>
                            <Td>
                                {die.jobs_run_count}
                                {die.estimated_lifespan_jobs
                                    ? ` / ${die.estimated_lifespan_jobs}`
                                    : ''}
                            </Td>
                            <Td className="text-left whitespace-nowrap">
                                {can('manage-catalog') && (
                                    <>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                href={CuttingDieController.edit(
                                                    die.id,
                                                )}
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <DeleteButton
                                            url={
                                                CuttingDieController.destroy(
                                                    die.id,
                                                ).url
                                            }
                                        />
                                    </>
                                )}
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </PageBody>
    );
}

DiesIndex.layout = {
    breadcrumbs: [{ title: 'الاسطمبات', href: CuttingDieController.index() }],
};
