import { Form, Head, Link } from '@inertiajs/react';
import CuttingDieController from '@/actions/App/Http/Controllers/Catalog/CuttingDieController';
import { Field, FormGrid, NativeSelect } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

type Die = {
    id: number;
    code: string;
    name: string;
    length_cm: string;
    width_cm: string;
    depth_cm: string;
    closure_type: string;
    rack_location: string | null;
    ups_on_cut_sheet: number;
    cut_fraction: string;
    condition: string;
    jobs_run_count: number;
    estimated_lifespan_jobs: number | null;
};

export default function DieForm({
    die,
    closureTypes,
    cutFractions,
    conditions,
}: {
    die: Die | null;
    closureTypes: Option[];
    cutFractions: Option[];
    conditions: Option[];
}) {
    const action = die
        ? CuttingDieController.update.form(die.id)
        : CuttingDieController.store.form();

    return (
        <PageBody>
            <Head title={die ? 'تعديل اسطمبة' : 'اسطمبة جديدة'} />
            <PageHeader title={die ? `تعديل: ${die.name}` : 'اسطمبة جديدة'} />

            <Form {...action} className="max-w-3xl space-y-6">
                {({ errors, processing }) => (
                    <>
                        <FormGrid>
                            <Field
                                label="الكود"
                                htmlFor="code"
                                error={errors.code}
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    dir="ltr"
                                    defaultValue={die?.code}
                                    placeholder="D-301810"
                                    required
                                />
                            </Field>
                            <Field
                                label="الاسم"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={die?.name}
                                    required
                                />
                            </Field>
                            <Field
                                label="الطول (سم)"
                                htmlFor="length_cm"
                                error={errors.length_cm}
                            >
                                <Input
                                    id="length_cm"
                                    name="length_cm"
                                    type="number"
                                    step="0.01"
                                    defaultValue={die?.length_cm}
                                    required
                                />
                            </Field>
                            <Field
                                label="العرض (سم)"
                                htmlFor="width_cm"
                                error={errors.width_cm}
                            >
                                <Input
                                    id="width_cm"
                                    name="width_cm"
                                    type="number"
                                    step="0.01"
                                    defaultValue={die?.width_cm}
                                    required
                                />
                            </Field>
                            <Field
                                label="الارتفاع / العمق (سم)"
                                htmlFor="depth_cm"
                                error={errors.depth_cm}
                            >
                                <Input
                                    id="depth_cm"
                                    name="depth_cm"
                                    type="number"
                                    step="0.01"
                                    defaultValue={die?.depth_cm}
                                    required
                                />
                            </Field>
                            <Field
                                label="نوع القفل"
                                htmlFor="closure_type"
                                error={errors.closure_type}
                            >
                                <NativeSelect
                                    id="closure_type"
                                    name="closure_type"
                                    options={closureTypes}
                                    defaultValue={die?.closure_type}
                                />
                            </Field>
                            <Field
                                label="عدد العلب في الشابلونة"
                                htmlFor="ups_on_cut_sheet"
                                error={errors.ups_on_cut_sheet}
                            >
                                <Input
                                    id="ups_on_cut_sheet"
                                    name="ups_on_cut_sheet"
                                    type="number"
                                    min={1}
                                    defaultValue={die?.ups_on_cut_sheet}
                                    required
                                />
                            </Field>
                            <Field
                                label="مقاس الفرخ"
                                htmlFor="cut_fraction"
                                error={errors.cut_fraction}
                            >
                                <NativeSelect
                                    id="cut_fraction"
                                    name="cut_fraction"
                                    options={cutFractions}
                                    defaultValue={die?.cut_fraction ?? '1/2'}
                                />
                            </Field>
                            <Field
                                label="مكانها في المخزن"
                                htmlFor="rack_location"
                                error={errors.rack_location}
                            >
                                <Input
                                    id="rack_location"
                                    name="rack_location"
                                    defaultValue={die?.rack_location ?? ''}
                                    placeholder="ستاند أ - رف 3"
                                />
                            </Field>
                            <Field
                                label="الحالة"
                                htmlFor="condition"
                                error={errors.condition}
                            >
                                <NativeSelect
                                    id="condition"
                                    name="condition"
                                    options={conditions}
                                    defaultValue={die?.condition ?? 'ready'}
                                />
                            </Field>
                            <Field
                                label="عدد مرات التشغيل"
                                htmlFor="jobs_run_count"
                                error={errors.jobs_run_count}
                            >
                                <Input
                                    id="jobs_run_count"
                                    name="jobs_run_count"
                                    type="number"
                                    min={0}
                                    defaultValue={die?.jobs_run_count ?? 0}
                                />
                            </Field>
                            <Field
                                label="العمر الافتراضي (عدد شغلانات)"
                                htmlFor="estimated_lifespan_jobs"
                                error={errors.estimated_lifespan_jobs}
                                hint="اختياري — للتنبيه قبل ما الاسطمبة تستهلك"
                            >
                                <Input
                                    id="estimated_lifespan_jobs"
                                    name="estimated_lifespan_jobs"
                                    type="number"
                                    min={1}
                                    defaultValue={
                                        die?.estimated_lifespan_jobs ?? ''
                                    }
                                />
                            </Field>
                        </FormGrid>
                        <div className="flex gap-2">
                            <Button disabled={processing}>حفظ</Button>
                            <Button asChild variant="ghost">
                                <Link href={CuttingDieController.index()}>
                                    إلغاء
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

DieForm.layout = {
    breadcrumbs: [{ title: 'الاسطمبات', href: CuttingDieController.index() }],
};
