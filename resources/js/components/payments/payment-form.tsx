import { Form } from '@inertiajs/react';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
import { Field, NativeSelect } from '@/components/crud/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

/** Record a payment for a customer, optionally against one of their jobs. */
export function PaymentForm({
    customerId,
    methods,
    jobs = [],
    jobId = null,
}: {
    customerId: number;
    methods: Option[];
    /** Jobs to choose from (customer page); omit when jobId is fixed (job page). */
    jobs?: { id: number; name: string }[];
    jobId?: number | null;
}) {
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Form
            {...PaymentController.store.form(customerId)}
            options={{ preserveScroll: true }}
            resetOnSuccess={['amount_egp', 'reference', 'notes']}
            className="grid gap-3 md:grid-cols-3"
        >
            {({ errors, processing }) => (
                <>
                    <Field
                        label="المبلغ (ج)"
                        htmlFor="amount_egp"
                        error={errors.amount_egp}
                    >
                        <Input
                            id="amount_egp"
                            name="amount_egp"
                            type="number"
                            step="0.01"
                            min={0}
                            required
                        />
                    </Field>
                    <Field
                        label="طريقة الدفع"
                        htmlFor="method"
                        error={errors.method}
                    >
                        <NativeSelect
                            id="method"
                            name="method"
                            options={methods}
                            defaultValue="cash"
                        />
                    </Field>
                    <Field
                        label="التاريخ"
                        htmlFor="paid_at"
                        error={errors.paid_at}
                    >
                        <Input
                            id="paid_at"
                            name="paid_at"
                            type="date"
                            dir="ltr"
                            defaultValue={today}
                            max={today}
                            required
                        />
                    </Field>
                    {jobId !== null ? (
                        <input type="hidden" name="job_id" value={jobId} />
                    ) : (
                        <Field
                            label="على شغلانة (اختياري)"
                            htmlFor="job_id"
                            error={errors.job_id}
                        >
                            <NativeSelect
                                id="job_id"
                                name="job_id"
                                placeholder="دفعة عامة على الحساب"
                                options={jobs.map((j) => ({
                                    value: String(j.id),
                                    label: `#${j.id} ${j.name}`,
                                }))}
                            />
                        </Field>
                    )}
                    <Field
                        label="رقم التحويل / الشيك"
                        htmlFor="reference"
                        error={errors.reference}
                    >
                        <Input id="reference" name="reference" dir="ltr" />
                    </Field>
                    <Field label="ملاحظات" htmlFor="notes" error={errors.notes}>
                        <Input id="notes" name="notes" />
                    </Field>
                    <div className="md:col-span-3">
                        <Button disabled={processing}>سجّل الدفعة</Button>
                    </div>
                </>
            )}
        </Form>
    );
}
