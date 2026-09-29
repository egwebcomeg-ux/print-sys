import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import PaperSupplierController from '@/actions/App/Http/Controllers/Catalog/PaperSupplierController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';

type Supplier = {
    id: number;
    name: string;
    contact_note: string | null;
    performance_notes: string | null;
    prices_count: number;
};

export default function PaperSuppliersIndex({ suppliers }: { suppliers: Supplier[] }) {
    const can = useCan();

    return (
        <PageBody>
            <Head title="موردين الورق" />
            <PageHeader
                title="موردين الورق"
                description="كل مورد ليه أسعار على كذا جرام — الأسعار نفسها بتتعدل من صفحة أنواع الورق"
                actions={
                    can('manage-catalog') && (
                        <Button asChild>
                            <Link href={PaperSupplierController.create()}>
                                <Plus /> مورد جديد
                            </Link>
                        </Button>
                    )
                }
            />
            <DataTable>
                <thead>
                    <tr>
                        <Th>المورد</Th>
                        <Th>التواصل</Th>
                        <Th>ملاحظات الأداء</Th>
                        <Th>عدد الأسعار</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {suppliers.length === 0 && <EmptyRow colSpan={5}>مفيش موردين لسه</EmptyRow>}
                    {suppliers.map((supplier) => (
                        <tr key={supplier.id}>
                            <Td className="font-medium">{supplier.name}</Td>
                            <Td>{supplier.contact_note ?? '—'}</Td>
                            <Td className="max-w-xs truncate">{supplier.performance_notes ?? '—'}</Td>
                            <Td>{supplier.prices_count}</Td>
                            <Td className="text-left whitespace-nowrap">
                                {can('manage-catalog') && (
                                    <>
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={PaperSupplierController.edit(supplier.id)}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <DeleteButton
                                            url={PaperSupplierController.destroy(supplier.id).url}
                                            confirmText="مسح المورد هيمسح كل أسعاره. متأكد؟"
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

PaperSuppliersIndex.layout = {
    breadcrumbs: [{ title: 'موردين الورق', href: PaperSupplierController.index() }],
};
