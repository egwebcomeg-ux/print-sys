import { Form, Head, Link } from '@inertiajs/react';
import PaperSupplierController from '@/actions/App/Http/Controllers/Catalog/PaperSupplierController';
import { Field, Textarea } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Supplier = {
    id: number;
    name: string;
    contact_note: string | null;
    performance_notes: string | null;
};

export default function PaperSupplierForm({ supplier }: { supplier: Supplier | null }) {
    const action = supplier
        ? PaperSupplierController.update.form(supplier.id)
        : PaperSupplierController.store.form();

    return (
        <PageBody>
            <Head title={supplier ? 'تعديل مورد' : 'مورد جديد'} />
            <PageHeader title={supplier ? `تعديل: ${supplier.name}` : 'مورد ورق جديد'} />

            <Form {...action} className="max-w-2xl space-y-6">
                {({ errors, processing }) => (
                    <>
                        <Field label="اسم المورد" htmlFor="name" error={errors.name}>
                            <Input id="name" name="name" defaultValue={supplier?.name} required />
                        </Field>
                        <Field label="التواصل" htmlFor="contact_note" error={errors.contact_note} hint="رقم تليفون أو اسم المسؤول">
                            <Input id="contact_note" name="contact_note" defaultValue={supplier?.contact_note ?? ''} />
                        </Field>
                        <Field label="ملاحظات الأداء" htmlFor="performance_notes" error={errors.performance_notes} hint="التزام بالمواعيد، جودة، مشاكل سابقة...">
                            <Textarea id="performance_notes" name="performance_notes" defaultValue={supplier?.performance_notes ?? ''} />
                        </Field>
                        <div className="flex gap-2">
                            <Button disabled={processing}>حفظ</Button>
                            <Button asChild variant="ghost">
                                <Link href={PaperSupplierController.index()}>إلغاء</Link>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

PaperSupplierForm.layout = {
    breadcrumbs: [{ title: 'موردين الورق', href: PaperSupplierController.index() }],
};
