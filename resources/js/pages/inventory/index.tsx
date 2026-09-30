import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InventoryController from '@/actions/App/Http/Controllers/InventoryController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { Field, NativeSelect } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { StockBadge } from '@/components/inventory/stock-badge';
import type { StockRow } from '@/components/inventory/stock-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { num } from '@/lib/format';
import type { Option } from '@/types';

function NewStockForm({
    grammages,
}: {
    grammages: (Option & { sheet: [number, number] })[];
}) {
    const [sheet, setSheet] = useState<[number, number] | null>(null);

    return (
        <Form
            {...InventoryController.store.form()}
            className="grid gap-3 md:grid-cols-6"
        >
            {({ errors, processing }) => (
                <>
                    <Field
                        label="الورق"
                        htmlFor="paper_grammage_id"
                        error={errors.paper_grammage_id}
                        className="md:col-span-2"
                    >
                        <NativeSelect
                            id="paper_grammage_id"
                            name="paper_grammage_id"
                            options={grammages}
                            placeholder="اختار..."
                            required
                            onChange={(e) =>
                                setSheet(
                                    grammages.find(
                                        (g) => g.value === e.target.value,
                                    )?.sheet ?? null,
                                )
                            }
                        />
                    </Field>
                    <Field
                        label="عرض الفرخ (سم)"
                        htmlFor="sheet_width_cm"
                        error={errors.sheet_width_cm}
                    >
                        <Input
                            id="sheet_width_cm"
                            name="sheet_width_cm"
                            type="number"
                            step="0.5"
                            required
                            key={`w${sheet?.[0]}`}
                            defaultValue={sheet?.[0]}
                        />
                    </Field>
                    <Field
                        label="طول الفرخ (سم)"
                        htmlFor="sheet_height_cm"
                        error={errors.sheet_height_cm}
                    >
                        <Input
                            id="sheet_height_cm"
                            name="sheet_height_cm"
                            type="number"
                            step="0.5"
                            required
                            key={`h${sheet?.[1]}`}
                            defaultValue={sheet?.[1]}
                        />
                    </Field>
                    <Field
                        label="الرصيد الحالي (فرخ)"
                        htmlFor="quantity_sheets"
                        error={errors.quantity_sheets}
                    >
                        <Input
                            id="quantity_sheets"
                            name="quantity_sheets"
                            type="number"
                            min={0}
                            defaultValue={0}
                            required
                        />
                    </Field>
                    <Field
                        label="حد الطلب (فرخ)"
                        htmlFor="reorder_level"
                        error={errors.reorder_level}
                        hint="ينبّه لما الرصيد يوصله"
                    >
                        <Input
                            id="reorder_level"
                            name="reorder_level"
                            type="number"
                            min={0}
                            defaultValue={0}
                        />
                    </Field>
                    <Field
                        label="المكان في المخزن"
                        htmlFor="location"
                        error={errors.location}
                        className="md:col-span-2"
                    >
                        <Input
                            id="location"
                            name="location"
                            placeholder="مثلاً: رف B3"
                        />
                    </Field>
                    <div className="flex items-end md:col-span-4">
                        <Button disabled={processing}>إضافة للمخزن</Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function InventoryIndex({
    stocks,
    grammages,
}: {
    stocks: StockRow[];
    grammages: (Option & { sheet: [number, number] })[];
}) {
    const can = useCan();
    const lowCount = stocks.filter((s) => s.low).length;

    return (
        <PageBody>
            <Head title="مخزن الورق" />
            <PageHeader
                title="مخزن الورق"
                description={
                    lowCount > 0
                        ? `${lowCount} صنف محتاج طلب · الصرف بيتم أوتوماتيك لما الشغلانة تتعمد`
                        : 'الصرف بيتم أوتوماتيك لما الشغلانة تتعمد (للأصناف المتسجلة هنا بس)'
                }
            />

            {can('manage-inventory') && (
                <Card className="mb-6 gap-3 py-4">
                    <CardHeader className="px-4">
                        <CardTitle className="text-base">صنف جديد</CardTitle>
                    </CardHeader>
                    <CardContent className="px-4">
                        <NewStockForm grammages={grammages} />
                    </CardContent>
                </Card>
            )}

            <DataTable>
                <thead>
                    <tr>
                        <Th>الورق</Th>
                        <Th>الجرام</Th>
                        <Th>مقاس الفرخ</Th>
                        <Th>الرصيد (فرخ)</Th>
                        <Th>حد الطلب</Th>
                        <Th>المكان</Th>
                        <Th>الحالة</Th>
                    </tr>
                </thead>
                <tbody>
                    {stocks.length === 0 && (
                        <EmptyRow colSpan={7}>
                            مفيش أصناف متسجلة في المخزن لسه
                        </EmptyRow>
                    )}
                    {stocks.map((s) => (
                        <tr key={s.id}>
                            <Td>
                                <Link
                                    href={InventoryController.show(s.id)}
                                    className="font-medium hover:text-emerald-400"
                                >
                                    {s.paper}
                                </Link>
                            </Td>
                            <Td>{s.gsm}</Td>
                            <Td dir="ltr" className="text-right">
                                {s.size}
                            </Td>
                            <Td
                                className={
                                    s.quantity < 0
                                        ? 'font-semibold text-red-400'
                                        : 'font-semibold'
                                }
                            >
                                {num(s.quantity)}
                            </Td>
                            <Td>
                                {s.reorderLevel > 0 ? num(s.reorderLevel) : '—'}
                            </Td>
                            <Td>{s.location ?? '—'}</Td>
                            <Td>
                                <StockBadge row={s} />
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </PageBody>
    );
}
