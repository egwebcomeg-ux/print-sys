import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import JobEditController from '@/actions/App/Http/Controllers/Jobs/JobEditController';
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

type ManualInitial = Pick<
    ManualJobQuote,
    'lineItems' | 'costLines' | 'marginPercent' | 'producedQuantity'
> & { title: string | null };

export default function CreateManualJob({
    papers,
    customers,
    defaultMarginPercent,
    preselect,
    editing = null,
    initial = null,
}: {
    papers: PaperType[];
    customers: CustomerOption[];
    defaultMarginPercent: number;
    preselect: { customerId: number | null; leadId: number | null };
    /** Set when re-pricing an existing job (jobs/{job}/edit). */
    editing?: { id: number; name: string } | null;
    initial?: ManualInitial | null;
}) {
    const [customerId, setCustomerId] = useState<number | null>(
        preselect.customerId,
    );
    const [title, setTitle] = useState(initial?.title ?? '');
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

        const visit = editing
            ? {
                  method: 'put' as const,
                  url: JobEditController.updateManual.url(editing.id),
              }
            : {
                  method: 'post' as const,
                  url: ManualJobController.store.url(),
              };

        router[visit.method](
            visit.url,
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
                    // The confirm button sits far down the page — make the failure visible.
                    toast.error(Object.values(e)[0] ?? 'راجع البيانات');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            },
        );
    };

    return (
        <PageBody>
            <Head
                title={editing ? `إعادة تسعير #${editing.id}` : 'حساب يدوي'}
            />
            <PageHeader
                title={
                    editing
                        ? `إعادة تسعير #${editing.id} — ${editing.name}`
                        : 'شغلانة ورقية — حساب يدوي'
                }
                description={
                    editing
                        ? 'الأسعار الحالية من الكتالوج. لما تأكد، الشغلانة بتتحدث وترجع مسودة.'
                        : 'للكتيبات والفلايرز والشغل الورقي اللي مقاسه وعدده معروفين. التكلفة بتتحسب تاني على السيرفر من أسعار الورق المسجلة.'
                }
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
                    initial={initial}
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
