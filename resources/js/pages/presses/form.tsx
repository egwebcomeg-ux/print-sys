import { Form, Head, Link } from '@inertiajs/react';
import PressController from '@/actions/App/Http/Controllers/Catalog/PressController';
import { Field, FormGrid } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

type Press = {
    id: number;
    name: string;
    is_internal: boolean;
    max_colors: number;
    supported_cut_fractions: string[];
    supported_paper_categories: string[] | null;
    current_backlog_days: number;
    contact_note: string | null;
};

function CheckboxGroup({
    name,
    options,
    selected,
}: {
    name: string;
    options: Option[];
    selected: string[];
}) {
    return (
        <div className="flex flex-wrap gap-3">
            {options.map((option) => (
                <label
                    key={option.value}
                    className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm"
                >
                    <input
                        type="checkbox"
                        name={`${name}[]`}
                        value={option.value}
                        defaultChecked={selected.includes(option.value)}
                        className="accent-emerald-500"
                    />
                    {option.label}
                </label>
            ))}
        </div>
    );
}

export default function PressForm({
    press,
    cutFractions,
    paperCategories,
}: {
    press: Press | null;
    cutFractions: Option[];
    paperCategories: Option[];
}) {
    const action = press
        ? PressController.update.form(press.id)
        : PressController.store.form();

    return (
        <PageBody>
            <Head title={press ? 'تعديل مطبعة' : 'مطبعة جديدة'} />
            <PageHeader
                title={press ? `تعديل: ${press.name}` : 'مطبعة / مقاول جديد'}
            />

            <Form {...action} className="max-w-3xl space-y-6">
                {({ errors, processing }) => (
                    <>
                        <FormGrid>
                            <Field
                                label="الاسم"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={press?.name}
                                    required
                                />
                            </Field>
                            <Field
                                label="أقصى عدد ألوان"
                                htmlFor="max_colors"
                                error={errors.max_colors}
                            >
                                <Input
                                    id="max_colors"
                                    name="max_colors"
                                    type="number"
                                    min={1}
                                    max={12}
                                    defaultValue={press?.max_colors ?? 4}
                                    required
                                />
                            </Field>
                            <Field
                                label="الشغل اللي قدامها (أيام)"
                                htmlFor="current_backlog_days"
                                error={errors.current_backlog_days}
                            >
                                <Input
                                    id="current_backlog_days"
                                    name="current_backlog_days"
                                    type="number"
                                    min={0}
                                    defaultValue={
                                        press?.current_backlog_days ?? 0
                                    }
                                />
                            </Field>
                            <Field
                                label="التواصل"
                                htmlFor="contact_note"
                                error={errors.contact_note}
                            >
                                <Input
                                    id="contact_note"
                                    name="contact_note"
                                    defaultValue={press?.contact_note ?? ''}
                                />
                            </Field>
                        </FormGrid>

                        <label className="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_internal" value="0" />
                            <input
                                type="checkbox"
                                name="is_internal"
                                value="1"
                                defaultChecked={press?.is_internal ?? false}
                                className="accent-emerald-500"
                            />
                            مطبعة داخلية (مش مقاول خارجي)
                        </label>

                        <div className="grid gap-2">
                            <span className="text-sm font-medium">
                                مقاسات الفرخ اللي بتشتغل بيها
                            </span>
                            <CheckboxGroup
                                name="supported_cut_fractions"
                                options={cutFractions}
                                selected={press?.supported_cut_fractions ?? []}
                            />
                            <InputError
                                message={errors.supported_cut_fractions}
                            />
                        </div>

                        <div className="grid gap-2">
                            <span className="text-sm font-medium">
                                أنواع الورق اللي بتقبلها
                            </span>
                            <p className="text-xs text-muted-foreground">
                                سيبها كلها فاضية لو بتقبل كل الأنواع
                            </p>
                            <CheckboxGroup
                                name="supported_paper_categories"
                                options={paperCategories}
                                selected={
                                    press?.supported_paper_categories ?? []
                                }
                            />
                            <InputError
                                message={errors.supported_paper_categories}
                            />
                        </div>

                        <div className="flex gap-2">
                            <Button disabled={processing}>حفظ</Button>
                            <Button asChild variant="ghost">
                                <Link href={PressController.index()}>
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

PressForm.layout = {
    breadcrumbs: [{ title: 'المطابع', href: PressController.index() }],
};
