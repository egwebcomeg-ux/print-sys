import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import PaperTypeController from '@/actions/App/Http/Controllers/Catalog/PaperTypeController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { egp } from '@/lib/format';

type PaperTypeRow = {
    id: number;
    name: string;
    categoryLabel: string;
    sheetSize: string;
    grammages: {
        id: number;
        gsm: number;
        pricesCount: number;
        cheapest: { supplierName: string; pricePerTonEgp: number } | null;
    }[];
};

export default function PaperTypesIndex({ paperTypes }: { paperTypes: PaperTypeRow[] }) {
    const can = useCan();

    return (
        <PageBody>
            <Head title="أنواع الورق" />
            <PageHeader
                title="أنواع الورق والأسعار"
                description="كل نوع ورق بجراماته، وكل جرام ليه سعر من كذا مورد — الأرخص بيظهر هنا كاقتراح"
                actions={
                    can('manage-catalog') && (
                        <Button asChild>
                            <Link href={PaperTypeController.create()}>
                                <Plus /> نوع ورق جديد
                            </Link>
                        </Button>
                    )
                }
            />
            <DataTable>
                <thead>
                    <tr>
                        <Th>النوع</Th>
                        <Th>الفئة</Th>
                        <Th>مقاس الفرخ</Th>
                        <Th>الجرامات (أرخص سعر للطن)</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {paperTypes.length === 0 && <EmptyRow colSpan={5}>مفيش أنواع ورق لسه</EmptyRow>}
                    {paperTypes.map((type) => (
                        <tr key={type.id}>
                            <Td className="font-medium">{type.name}</Td>
                            <Td>{type.categoryLabel}</Td>
                            <Td dir="ltr" className="text-right">{type.sheetSize}</Td>
                            <Td>
                                <div className="flex flex-wrap gap-1.5">
                                    {type.grammages.length === 0 && (
                                        <span className="text-muted-foreground">مفيش جرامات</span>
                                    )}
                                    {type.grammages.map((g) => (
                                        <Badge key={g.id} variant="secondary" className="font-normal">
                                            {g.gsm} جم
                                            {g.cheapest ? ` · ${egp(g.cheapest.pricePerTonEgp)}` : ' · بدون سعر'}
                                            {g.pricesCount > 1 && (
                                                <span className="text-emerald-400"> ({g.pricesCount} موردين)</span>
                                            )}
                                        </Badge>
                                    ))}
                                </div>
                            </Td>
                            <Td className="text-left whitespace-nowrap">
                                {can('manage-paper-prices') && (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={PaperTypeController.edit(type.id)}>
                                            <Pencil className="size-4" /> الأسعار
                                        </Link>
                                    </Button>
                                )}
                                {can('manage-catalog') && (
                                    <DeleteButton url={PaperTypeController.destroy(type.id).url} />
                                )}
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </PageBody>
    );
}

PaperTypesIndex.layout = {
    breadcrumbs: [{ title: 'أنواع الورق', href: PaperTypeController.index() }],
};
