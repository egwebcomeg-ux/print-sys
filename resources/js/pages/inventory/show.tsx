import { Form, Head, Link } from '@inertiajs/react';
import InventoryController from '@/actions/App/Http/Controllers/InventoryController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { Field, NativeSelect } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Pagination } from '@/components/crud/pagination';
import { StockBadge } from '@/components/inventory/stock-badge';
import type { StockRow } from '@/components/inventory/stock-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { date, num } from '@/lib/format';
import type { Option, Paginated } from '@/types';

type Movement = {
    id: number;
    type: 'receipt' | 'consumption' | 'adjustment';
    typeLabel: string;
    quantity: number;
    balanceAfter: number;
    jobId: number | null;
    supplier: string | null;
    user: string | null;
    note: string | null;
    at: string | null;
};

export default function InventoryShow({
    stock,
    movements,
    suppliers,
}: {
    stock: StockRow;
    movements: Paginated<Movement>;
    suppliers: Option[];
}) {
    const can = useCan();

    return (
        <PageBody>
            <Head title={stock.label} />
            <PageHeader
                title={stock.label}
                description={
                    stock.location ? `المكان: ${stock.location}` : undefined
                }
            />

            <div className="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-border bg-card px-4 py-3">
                <div>
                    <div className="text-xs text-muted-foreground">الرصيد</div>
                    <div
                        className={`text-3xl font-semibold tabular-nums ${stock.quantity < 0 ? 'text-red-400' : ''}`}
                    >
                        {num(stock.quantity)}{' '}
                        <span className="text-base font-normal text-muted-foreground">
                            فرخ
                        </span>
                    </div>
                </div>
                <StockBadge row={stock} />
            </div>

            {can('manage-inventory') && (
                <div className="mb-6 grid gap-4 lg:grid-cols-3">
                    <Card className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-base">
                                استلام ورق
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4">
                            <Form
                                {...InventoryController.receive.form(stock.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <Field
                                            label="العدد (فرخ)"
                                            htmlFor="quantity"
                                            error={errors.quantity}
                                        >
                                            <Input
                                                id="quantity"
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                required
                                            />
                                        </Field>
                                        <Field
                                            label="المورد"
                                            htmlFor="paper_supplier_id"
                                            error={errors.paper_supplier_id}
                                        >
                                            <NativeSelect
                                                id="paper_supplier_id"
                                                name="paper_supplier_id"
                                                options={suppliers}
                                                placeholder="—"
                                            />
                                        </Field>
                                        <Field
                                            label="ملاحظة / رقم الفاتورة"
                                            htmlFor="receive_note"
                                            error={errors.note}
                                        >
                                            <Input
                                                id="receive_note"
                                                name="note"
                                            />
                                        </Field>
                                        <Button disabled={processing}>
                                            سجّل الاستلام
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-base">
                                جرد / تسوية
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4">
                            <Form
                                {...InventoryController.adjust.form(stock.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <Field
                                            label="العدد الفعلي بعد الجرد"
                                            htmlFor="counted"
                                            error={errors.counted}
                                        >
                                            <Input
                                                id="counted"
                                                name="counted"
                                                type="number"
                                                min={0}
                                                required
                                            />
                                        </Field>
                                        <Field
                                            label="السبب"
                                            htmlFor="adjust_note"
                                            error={errors.note}
                                        >
                                            <Input
                                                id="adjust_note"
                                                name="note"
                                                placeholder="جرد شهري، تالف، ..."
                                                required
                                            />
                                        </Field>
                                        <Button
                                            disabled={processing}
                                            variant="secondary"
                                        >
                                            سجّل الجرد
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-base">
                                الإعدادات
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4">
                            <Form
                                {...InventoryController.update.form(stock.id)}
                                options={{ preserveScroll: true }}
                                className="grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <Field
                                            label="حد الطلب (فرخ)"
                                            htmlFor="reorder_level"
                                            error={errors.reorder_level}
                                        >
                                            <Input
                                                id="reorder_level"
                                                name="reorder_level"
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    stock.reorderLevel
                                                }
                                                required
                                            />
                                        </Field>
                                        <Field
                                            label="المكان في المخزن"
                                            htmlFor="location"
                                            error={errors.location}
                                        >
                                            <Input
                                                id="location"
                                                name="location"
                                                defaultValue={
                                                    stock.location ?? ''
                                                }
                                            />
                                        </Field>
                                        <Button
                                            disabled={processing}
                                            variant="secondary"
                                        >
                                            حفظ
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            )}

            <h2 className="mb-3 text-base font-semibold">حركة الصنف</h2>
            <DataTable>
                <thead>
                    <tr>
                        <Th>التاريخ</Th>
                        <Th>الحركة</Th>
                        <Th>الكمية</Th>
                        <Th>الرصيد بعدها</Th>
                        <Th>تفاصيل</Th>
                        <Th>بواسطة</Th>
                    </tr>
                </thead>
                <tbody>
                    {movements.data.length === 0 && (
                        <EmptyRow colSpan={6}>مفيش حركة لسه</EmptyRow>
                    )}
                    {movements.data.map((m) => (
                        <tr key={m.id}>
                            <Td>{date(m.at)}</Td>
                            <Td>{m.typeLabel}</Td>
                            <Td
                                dir="ltr"
                                className={`text-right font-medium ${m.quantity < 0 ? 'text-red-400' : 'text-emerald-400'}`}
                            >
                                {m.quantity > 0
                                    ? `+${num(m.quantity)}`
                                    : num(m.quantity)}
                            </Td>
                            <Td>{num(m.balanceAfter)}</Td>
                            <Td>
                                {m.jobId && (
                                    <Link
                                        href={JobController.show(m.jobId)}
                                        className="text-emerald-400 hover:underline"
                                    >
                                        #{m.jobId}
                                    </Link>
                                )}
                                {m.supplier && <span>{m.supplier}</span>}
                                {m.note && (
                                    <div className="text-xs text-muted-foreground">
                                        {m.note}
                                    </div>
                                )}
                            </Td>
                            <Td>{m.user ?? '—'}</Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
            <Pagination page={movements} />
        </PageBody>
    );
}

InventoryShow.layout = {
    breadcrumbs: [{ title: 'مخزن الورق', href: InventoryController.index() }],
};
