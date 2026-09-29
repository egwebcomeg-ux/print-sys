import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import ManualJobController from '@/actions/App/Http/Controllers/Jobs/ManualJobController';
import { Field } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import ManualJobCostingCalculator from '@/components/costing/ManualJobCostingCalculator';
import type {
    ManualJobQuote,
    PaperType,
} from '@/components/costing/ManualJobCostingCalculator';
import { CustomerPicker } from '@/components/jobs/customer-picker';
import type { CustomerOption } from '@/components/jobs/customer-picker';
import { ValidationSummary } from '@/components/jobs/validation-summary';
import { Input } from '@/components/ui/input';
import { asPayload } from '@/lib/payload';

export default function CreateManualJob({
    papers,
    customers,
    defaultMarginPercent,
    preselect,
}: {
    papers: PaperType[];
    customers: CustomerOption[];
    defaultMarginPercent: number;
    preselect: { customerId: number | null; leadId: number | null };
}) {
    const [customerId, setCustomerId] = useState<number | null>(
        preselect.customerId,
    );
    const [title, setTitle] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const confirm = (quote: ManualJobQuote) => {
        const missing: Record<string, string> = {};
        if (!customerId) {
            missing.customer_id = 'اختار العميل الأول';
        }
        if (!title.trim()) {
            missing.title = 'اكتب اسم الشغلانة (مثال: كتيب 16 صفحة)';
        }
        if (Object.keys(missing).length > 0) {
            setErrors(missing);
            window.scrollTo({ top: 0, behavior: 'smooth' });

            return;
        }

        router.post(
            ManualJobController.store.url(),
            asPayload({
                ...quote,
                customer_id: customerId,
                title,
                lead_id: preselect.leadId,
            }),
            {
                preserveScroll: true,
                onError: (e) => {
                    setErrors(e);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            },
        );
    };

    return (
        <PageBody>
            <Head title="حساب يدوي" />
            <PageHeader
                title="شغلانة ورقية — حساب يدوي"
                description="للكتيبات والفلايرز والشغل الورقي اللي مقاسه وعدده معروفين. التكلفة بتتحسب تاني على السيرفر من أسعار الورق المسجلة."
            />

            <ValidationSummary errors={errors} />

            <div className="mb-4 grid max-w-3xl gap-4 md:grid-cols-2">
                <CustomerPicker
                    customers={customers}
                    value={customerId}
                    onChange={setCustomerId}
                    error={errors.customer_id}
                />
                <Field label="اسم الشغلانة" htmlFor="title" error={errors.title}>
                    <Input
                        id="title"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="مثال: كتيب 16 صفحة A5"
                    />
                </Field>
            </div>

            <div className="max-w-4xl">
                <ManualJobCostingCalculator
                    papers={papers}
                    defaultMarginPercent={defaultMarginPercent}
                    onConfirmOrder={confirm}
                />
            </div>
        </PageBody>
    );
}

CreateManualJob.layout = {
    breadcrumbs: [
        { title: 'الشغلانات', href: JobController.index() },
        { title: 'حساب يدوي', href: ManualJobController.create() },
    ],
};
