import { Form, Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Catalog/CustomerController';
import { Field, FormGrid, Textarea } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Customer } from '@/types';

export default function CustomerForm({
    customer,
}: {
    customer: Customer | null;
}) {
    const action = customer
        ? CustomerController.update.form(customer.id)
        : CustomerController.store.form();

    return (
        <PageBody>
            <Head title={customer ? 'تعديل عميل' : 'عميل جديد'} />
            <PageHeader
                title={customer ? `تعديل: ${customer.name}` : 'عميل جديد'}
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
                                    defaultValue={customer?.name}
                                    required
                                />
                            </Field>
                            <Field
                                label="التليفون"
                                htmlFor="phone"
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    dir="ltr"
                                    defaultValue={customer?.phone ?? ''}
                                />
                            </Field>
                            <Field
                                label="الإيميل"
                                htmlFor="email"
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    dir="ltr"
                                    defaultValue={customer?.email ?? ''}
                                />
                            </Field>
                            <Field
                                label="حد الائتمان (ج)"
                                htmlFor="credit_limit_egp"
                                error={errors.credit_limit_egp}
                                hint="اختياري — سقف المديونية المسموح بيها"
                            >
                                <Input
                                    id="credit_limit_egp"
                                    name="credit_limit_egp"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    defaultValue={
                                        customer?.credit_limit_egp ?? ''
                                    }
                                />
                            </Field>
                        </FormGrid>
                        <Field
                            label="ملاحظات"
                            htmlFor="notes"
                            error={errors.notes}
                        >
                            <Textarea
                                id="notes"
                                name="notes"
                                defaultValue={customer?.notes ?? ''}
                            />
                        </Field>
                        <div className="flex gap-2">
                            <Button disabled={processing}>حفظ</Button>
                            <Button asChild variant="ghost">
                                <Link href={CustomerController.index()}>
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

CustomerForm.layout = {
    breadcrumbs: [{ title: 'العملاء', href: CustomerController.index() }],
};
