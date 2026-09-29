import { Form, Head, Link } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import PaperGrammageController from '@/actions/App/Http/Controllers/Catalog/PaperGrammageController';
import PaperGrammagePriceController from '@/actions/App/Http/Controllers/Catalog/PaperGrammagePriceController';
import PaperTypeController from '@/actions/App/Http/Controllers/Catalog/PaperTypeController';
import { DeleteButton } from '@/components/crud/delete-button';
import { Field, FormGrid, NativeSelect } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { egp } from '@/lib/format';
import type { Option } from '@/types';

type Price = {
    id: number;
    supplierId: number;
    supplierName: string;
    pricePerTonEgp: number;
    priceAsOf: string | null;
};

type Grammage = { id: number; gsm: number; prices: Price[] };

type PaperTypeData = {
    id: number;
    name: string;
    category: string;
    sheet_width_cm: number;
    sheet_height_cm: number;
    grammages: Grammage[];
};

export default function PaperTypeForm({
    paperType,
    categories,
    suppliers = [],
}: {
    paperType: PaperTypeData | null;
    categories: Option[];
    suppliers?: { id: number; name: string }[];
}) {
    const can = useCan();
    const canEditType = can('manage-catalog');
    const action = paperType
        ? PaperTypeController.update.form(paperType.id)
        : PaperTypeController.store.form();

    return (
        <PageBody>
            <Head title={paperType ? paperType.name : 'نوع ورق جديد'} />
            <PageHeader
                title={paperType ? paperType.name : 'نوع ورق جديد'}
                description={
                    paperType
                        ? 'الجرامات وأسعار كل مورد. الأرخص هو الاقتراح المبدئي في الحاسبات، والموظف يقدر يختار أي مورد.'
                        : undefined
                }
                actions={
                    <Button asChild variant="ghost">
                        <Link href={PaperTypeController.index()}>
                            <ChevronRight /> رجوع للقائمة
                        </Link>
                    </Button>
                }
            />

            <Form {...action} className="mb-8 max-w-3xl space-y-6">
                {({ errors, processing }) => (
                    <fieldset disabled={!canEditType} className="space-y-6">
                        <FormGrid>
                            <Field
                                label="اسم النوع"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={paperType?.name}
                                    required
                                />
                            </Field>
                            <Field
                                label="الفئة"
                                htmlFor="category"
                                error={errors.category}
                                hint="بتستخدم في فلترة المطابع اللي بتقبل نوع الورق"
                            >
                                <NativeSelect
                                    id="category"
                                    name="category"
                                    options={categories}
                                    defaultValue={
                                        paperType?.category ??
                                        categories[0]?.value
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
                                    min={10}
                                    defaultValue={
                                        paperType?.sheet_width_cm ?? 70
                                    }
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
                                    min={10}
                                    defaultValue={
                                        paperType?.sheet_height_cm ?? 100
                                    }
                                />
                            </Field>
                        </FormGrid>
                        {canEditType && (
                            <Button disabled={processing}>حفظ النوع</Button>
                        )}
                    </fieldset>
                )}
            </Form>

            {paperType && (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <h2 className="text-lg font-semibold">
                            الجرامات والأسعار
                        </h2>
                        {canEditType && (
                            <Form
                                {...PaperGrammageController.store.form(
                                    paperType.id,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="flex items-start gap-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div>
                                            <Input
                                                name="gsm"
                                                type="number"
                                                min={40}
                                                placeholder="جرام جديد، مثال 300"
                                                className="w-48"
                                            />
                                            <InputError message={errors.gsm} />
                                        </div>
                                        <Button
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            <Plus /> إضافة جرام
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>

                    {paperType.grammages.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            ضيف أول جرام للنوع ده.
                        </p>
                    )}

                    <div className="grid gap-4 lg:grid-cols-2">
                        {paperType.grammages.map((grammage) => (
                            <GrammageCard
                                key={grammage.id}
                                grammage={grammage}
                                suppliers={suppliers}
                                canDelete={canEditType}
                            />
                        ))}
                    </div>
                </div>
            )}
        </PageBody>
    );
}

function GrammageCard({
    grammage,
    suppliers,
    canDelete,
}: {
    grammage: Grammage;
    suppliers: { id: number; name: string }[];
    canDelete: boolean;
}) {
    const cheapestId = grammage.prices[0]?.id; // server orders prices ascending

    return (
        <Card className="gap-4 py-4">
            <CardHeader className="flex flex-row items-center justify-between px-4">
                <CardTitle className="text-base">{grammage.gsm} جرام</CardTitle>
                {canDelete && (
                    <DeleteButton
                        url={PaperGrammageController.destroy(grammage.id).url}
                        confirmText="مسح الجرام هيمسح كل أسعاره. متأكد؟"
                    />
                )}
            </CardHeader>
            <CardContent className="space-y-3 px-4">
                {grammage.prices.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        مفيش أسعار لسه.
                    </p>
                )}
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {grammage.prices.map((price) => (
                        <li
                            key={price.id}
                            className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                        >
                            <div className="flex items-center gap-2">
                                <span>{price.supplierName}</span>
                                {price.id === cheapestId &&
                                    grammage.prices.length > 1 && (
                                        <Badge className="bg-emerald-500/15 text-emerald-400">
                                            الأرخص
                                        </Badge>
                                    )}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium">
                                    {egp(price.pricePerTonEgp)} / طن
                                </span>
                                {price.priceAsOf && (
                                    <span className="text-xs text-muted-foreground">
                                        ({price.priceAsOf})
                                    </span>
                                )}
                                <DeleteButton
                                    size="icon"
                                    url={
                                        PaperGrammagePriceController.destroy(
                                            price.id,
                                        ).url
                                    }
                                />
                            </div>
                        </li>
                    ))}
                </ul>

                <Form
                    {...PaperGrammagePriceController.store.form(grammage.id)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['price_per_ton_egp']}
                    className="flex flex-wrap items-start gap-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <NativeSelect
                                name="paper_supplier_id"
                                options={suppliers.map((s) => ({
                                    value: String(s.id),
                                    label: s.name,
                                }))}
                                placeholder="اختار المورد"
                                className="w-44"
                                required
                            />
                            <div>
                                <Input
                                    name="price_per_ton_egp"
                                    type="number"
                                    min={1}
                                    step="0.01"
                                    placeholder="سعر الطن (ج)"
                                    className="w-36"
                                    required
                                />
                                <InputError
                                    message={
                                        errors.price_per_ton_egp ??
                                        errors.paper_supplier_id
                                    }
                                />
                            </div>
                            <Button
                                size="sm"
                                variant="secondary"
                                disabled={processing}
                                className="h-9"
                            >
                                حفظ السعر
                            </Button>
                        </>
                    )}
                </Form>
                <p className="text-xs text-muted-foreground">
                    لو المورد ليه سعر بالفعل على الجرام ده، السعر الجديد هيحل
                    محله.
                </p>
            </CardContent>
        </Card>
    );
}

PaperTypeForm.layout = {
    breadcrumbs: [{ title: 'أنواع الورق', href: PaperTypeController.index() }],
};
