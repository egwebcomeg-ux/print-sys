import { Form, Head } from '@inertiajs/react';
import CompanySettingsController from '@/actions/App/Http/Controllers/Admin/CompanySettingsController';
import { Field, FormGrid, Textarea } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type SettingRow = { key: string; label: string; value: string | number };

const multiline = new Set(['quote_notes', 'company_address']);
const numeric = new Set(['quote_vat_percent', 'quote_validity_days']);

export default function CompanySettings({
    settings,
}: {
    settings: SettingRow[];
}) {
    return (
        <PageBody>
            <Head title="بيانات المصنع" />
            <PageHeader
                title="بيانات المصنع"
                description="الترويسة والضريبة والملاحظات اللي بتتطبع على عرض السعر"
            />

            <Form
                {...CompanySettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="max-w-3xl space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <FormGrid>
                            {settings.map((setting) => (
                                <Field
                                    key={setting.key}
                                    label={setting.label}
                                    htmlFor={setting.key}
                                    error={errors[setting.key]}
                                    className={
                                        multiline.has(setting.key)
                                            ? 'md:col-span-2'
                                            : undefined
                                    }
                                >
                                    {multiline.has(setting.key) ? (
                                        <Textarea
                                            id={setting.key}
                                            name={setting.key}
                                            defaultValue={String(
                                                setting.value ?? '',
                                            )}
                                        />
                                    ) : (
                                        <Input
                                            id={setting.key}
                                            name={setting.key}
                                            type={
                                                numeric.has(setting.key)
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            step="any"
                                            dir={
                                                numeric.has(setting.key) ||
                                                setting.key ===
                                                    'company_phone' ||
                                                setting.key === 'company_email'
                                                    ? 'ltr'
                                                    : undefined
                                            }
                                            defaultValue={String(
                                                setting.value ?? '',
                                            )}
                                        />
                                    )}
                                </Field>
                            ))}
                        </FormGrid>
                        <Button disabled={processing}>حفظ</Button>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

CompanySettings.layout = {
    breadcrumbs: [
        { title: 'بيانات المصنع', href: CompanySettingsController.edit() },
    ],
};
