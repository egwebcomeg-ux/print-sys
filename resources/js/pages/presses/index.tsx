import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import PressController from '@/actions/App/Http/Controllers/Catalog/PressController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import type { Option } from '@/types';

type PressRow = {
    id: number;
    name: string;
    is_internal: boolean;
    max_colors: number;
    supported_cut_fractions: string[];
    supported_paper_categories: string[] | null;
    current_backlog_days: number;
    contact_note: string | null;
};

export default function PressesIndex({
    presses,
    paperCategories,
}: {
    presses: PressRow[];
    paperCategories: Option[];
}) {
    const can = useCan();
    const categoryLabel = (value: string) =>
        paperCategories.find((c) => c.value === value)?.label ?? value;

    return (
        <PageBody>
            <Head title="المطابع" />
            <PageHeader
                title="المطابع والمقاولين"
                description="قدرات كل مطبعة والشغل اللي قدامها. توزيع الشغلانات بيبقى يدوي دايمًا — السيستم بيفلتر ويرتب بس."
                actions={
                    can('manage-catalog') && (
                        <Button asChild>
                            <Link href={PressController.create()}>
                                <Plus /> مطبعة جديدة
                            </Link>
                        </Button>
                    )
                }
            />
            <DataTable>
                <thead>
                    <tr>
                        <Th>المطبعة</Th>
                        <Th>النوع</Th>
                        <Th>أقصى ألوان</Th>
                        <Th>مقاسات الفرخ</Th>
                        <Th>أنواع الورق</Th>
                        <Th>الشغل اللي قدامها (أيام)</Th>
                        <Th>التواصل</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {presses.length === 0 && <EmptyRow colSpan={8}>مفيش مطابع لسه</EmptyRow>}
                    {presses.map((press) => (
                        <tr key={press.id}>
                            <Td className="font-medium">{press.name}</Td>
                            <Td>
                                <Badge variant={press.is_internal ? 'default' : 'secondary'}>
                                    {press.is_internal ? 'داخلية' : 'مقاول خارجي'}
                                </Badge>
                            </Td>
                            <Td>{press.max_colors}</Td>
                            <Td dir="ltr" className="text-right">{press.supported_cut_fractions.join('، ')}</Td>
                            <Td>
                                {press.supported_paper_categories?.length
                                    ? press.supported_paper_categories.map(categoryLabel).join('، ')
                                    : 'كل الأنواع'}
                            </Td>
                            <Td>
                                {can('update-press-backlog') ? (
                                    <Input
                                        type="number"
                                        min={0}
                                        defaultValue={press.current_backlog_days}
                                        className="h-8 w-20"
                                        onBlur={(e) => {
                                            const days = Number(e.target.value);
                                            if (days !== press.current_backlog_days && days >= 0) {
                                                router.patch(
                                                    PressController.updateBacklog(press.id).url,
                                                    { current_backlog_days: days },
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    />
                                ) : (
                                    press.current_backlog_days
                                )}
                            </Td>
                            <Td>{press.contact_note ?? '—'}</Td>
                            <Td className="text-left whitespace-nowrap">
                                {can('manage-catalog') && (
                                    <>
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={PressController.edit(press.id)}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <DeleteButton url={PressController.destroy(press.id).url} />
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

PressesIndex.layout = {
    breadcrumbs: [{ title: 'المطابع', href: PressController.index() }],
};
