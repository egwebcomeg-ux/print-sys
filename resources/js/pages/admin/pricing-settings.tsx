import { Form, Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import PricingSettingsController from '@/actions/App/Http/Controllers/Admin/PricingSettingsController';
import { Field, FormGrid } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type SettingRow = {
    key: string;
    label: string;
    note: string | null;
    value: number | Record<string, number>;
};

export default function PricingSettings({
    settings,
}: {
    settings: SettingRow[];
}) {
    return (
        <PageBody>
            <Head title="ثوابت التسعير" />
            <PageHeader
                title="ثوابت التسعير"
                description="الأرقام دي بتستخدمها حاسبة العلب. نسبة الربح بتتكتب يدوي لكل شغلانة — القيمة هنا مجرد رقم مبدئي في الحاسبة."
            />

            <div className="mb-6 flex max-w-3xl items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-300">
                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                القيم دي أمثلة مبدئية — حط أسعار المصنع الحقيقية قبل ما تعتمد
                على التسعير.
            </div>

            <Form
                {...PricingSettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="max-w-3xl space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <FormGrid>
                            {settings.map((setting) =>
                                typeof setting.value === 'object' ? (
                                    Object.entries(setting.value).map(
                                        ([sub, value]) => (
                                            <Field
                                                key={`${setting.key}.${sub}`}
                                                label={`${setting.label} — ${sub === 'matte' ? 'مط' : sub === 'gloss' ? 'لامع' : sub}`}
                                                htmlFor={`${setting.key}.${sub}`}
                                                error={
                                                    errors[
                                                        `${setting.key}.${sub}`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`${setting.key}.${sub}`}
                                                    name={`${setting.key}[${sub}]`}
                                                    type="number"
                                                    step="any"
                                                    min={0}
                                                    defaultValue={value}
                                                />
                                            </Field>
                                        ),
                                    )
                                ) : (
                                    <Field
                                        key={setting.key}
                                        label={setting.label}
                                        htmlFor={setting.key}
                                        error={errors[setting.key]}
                                        hint={setting.note ?? undefined}
                                    >
                                        <Input
                                            id={setting.key}
                                            name={setting.key}
                                            type="number"
                                            step="any"
                                            min={0}
                                            defaultValue={setting.value}
                                        />
                                    </Field>
                                ),
                            )}
                        </FormGrid>
                        <Button disabled={processing}>حفظ</Button>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

PricingSettings.layout = {
    breadcrumbs: [
        { title: 'ثوابت التسعير', href: PricingSettingsController.edit() },
    ],
};
