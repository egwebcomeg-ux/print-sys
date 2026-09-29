import { Form, Head, Link } from '@inertiajs/react';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import {
    Field,
    FormGrid,
    NativeSelect,
    Textarea,
} from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

type Lead = {
    id: number;
    contact_name: string;
    company_name: string | null;
    phone: string | null;
    source: string | null;
    status: string;
    expected_quantity: number | null;
    notes: string | null;
    customer_id: number | null;
    owner_user_id: number | null;
};

export default function LeadForm({
    lead,
    statuses,
    customers,
    users,
}: {
    lead: Lead | null;
    statuses: Option[];
    customers: { id: number; name: string }[];
    users: { id: number; name: string }[];
}) {
    const action = lead
        ? LeadController.update.form(lead.id)
        : LeadController.store.form();

    return (
        <PageBody>
            <Head title={lead ? 'تعديل فرصة' : 'فرصة جديدة'} />
            <PageHeader
                title={lead ? `تعديل: ${lead.contact_name}` : 'فرصة جديدة'}
            />

            <Form {...action} className="max-w-3xl space-y-6">
                {({ errors, processing }) => (
                    <>
                        <FormGrid>
                            <Field
                                label="اسم جهة الاتصال"
                                htmlFor="contact_name"
                                error={errors.contact_name}
                            >
                                <Input
                                    id="contact_name"
                                    name="contact_name"
                                    defaultValue={lead?.contact_name}
                                    required
                                />
                            </Field>
                            <Field
                                label="الشركة"
                                htmlFor="company_name"
                                error={errors.company_name}
                            >
                                <Input
                                    id="company_name"
                                    name="company_name"
                                    defaultValue={lead?.company_name ?? ''}
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
                                    defaultValue={lead?.phone ?? ''}
                                />
                            </Field>
                            <Field
                                label="المصدر"
                                htmlFor="source"
                                error={errors.source}
                                hint="فيسبوك، ترشيح عميل، زيارة..."
                            >
                                <Input
                                    id="source"
                                    name="source"
                                    defaultValue={lead?.source ?? ''}
                                />
                            </Field>
                            <Field
                                label="الحالة"
                                htmlFor="status"
                                error={errors.status}
                            >
                                <NativeSelect
                                    id="status"
                                    name="status"
                                    options={statuses}
                                    defaultValue={lead?.status ?? 'new'}
                                />
                            </Field>
                            <Field
                                label="الكمية المتوقعة"
                                htmlFor="expected_quantity"
                                error={errors.expected_quantity}
                            >
                                <Input
                                    id="expected_quantity"
                                    name="expected_quantity"
                                    type="number"
                                    min={1}
                                    defaultValue={lead?.expected_quantity ?? ''}
                                />
                            </Field>
                            <Field
                                label="عميل موجود (اختياري)"
                                htmlFor="customer_id"
                                error={errors.customer_id}
                                hint="لو فاضي، هيتعمل عميل جديد لما تحوّلها لشغلانة"
                            >
                                <NativeSelect
                                    id="customer_id"
                                    name="customer_id"
                                    options={customers.map((c) => ({
                                        value: String(c.id),
                                        label: c.name,
                                    }))}
                                    placeholder="—"
                                    defaultValue={
                                        lead?.customer_id
                                            ? String(lead.customer_id)
                                            : ''
                                    }
                                />
                            </Field>
                            <Field
                                label="المسؤول"
                                htmlFor="owner_user_id"
                                error={errors.owner_user_id}
                            >
                                <NativeSelect
                                    id="owner_user_id"
                                    name="owner_user_id"
                                    options={users.map((u) => ({
                                        value: String(u.id),
                                        label: u.name,
                                    }))}
                                    placeholder="—"
                                    defaultValue={
                                        lead?.owner_user_id
                                            ? String(lead.owner_user_id)
                                            : ''
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
                                defaultValue={lead?.notes ?? ''}
                            />
                        </Field>
                        <div className="flex gap-2">
                            <Button disabled={processing}>حفظ</Button>
                            <Button asChild variant="ghost">
                                <Link href={LeadController.index()}>إلغاء</Link>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

LeadForm.layout = {
    breadcrumbs: [{ title: 'الفرص', href: LeadController.index() }],
};
