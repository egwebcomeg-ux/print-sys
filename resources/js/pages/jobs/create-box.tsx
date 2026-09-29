import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import BoxJobController from '@/actions/App/Http/Controllers/Jobs/BoxJobController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import JobEditController from '@/actions/App/Http/Controllers/Jobs/JobEditController';
import { Field } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import QuickBoxPricingCalculator from '@/components/costing/QuickBoxPricingCalculator';
import type {
    BoxQuote,
    DieCutTool,
    PaperType,
    PricingConstants,
    SavedJobSpec,
} from '@/components/costing/QuickBoxPricingCalculator';
import { CustomerPicker } from '@/components/jobs/customer-picker';
import type { CustomerOption } from '@/components/jobs/customer-picker';
import { ValidationSummary } from '@/components/jobs/validation-summary';
import { Input } from '@/components/ui/input';
import { asPayload } from '@/lib/payload';

export default function CreateBoxJob({
    dies,
    papers,
    pastJobs,
    customers,
    pricingConstants,
    defaultMarginPercent,
    preselect,
    editing = null,
    initial = null,
}: {
    dies: DieCutTool[];
    papers: PaperType[];
    pastJobs: SavedJobSpec[];
    customers: CustomerOption[];
    pricingConstants: PricingConstants;
    defaultMarginPercent: number;
    preselect: { customerId: number | null; leadId: number | null };
    /** Set when re-pricing an existing job (jobs/{job}/edit). */
    editing?: { id: number; name: string } | null;
    initial?: {
        title: string | null;
        job: SavedJobSpec;
        marginPercent: number;
    } | null;
}) {
    const [customerId, setCustomerId] = useState<number | null>(
        preselect.customerId,
    );
    const [title, setTitle] = useState(initial?.title ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const confirm = (quote: BoxQuote) => {
        if (!customerId) {
            setErrors({ customer_id: 'اختار العميل الأول' });
            window.scrollTo({ top: 0, behavior: 'smooth' });

            return;
        }

        const visit = editing
            ? {
                  method: 'put' as const,
                  url: JobEditController.updateBox.url(editing.id),
              }
            : { method: 'post' as const, url: BoxJobController.store.url() };

        router[visit.method](
            visit.url,
            // matchedDie (full object) is dropped: the server only needs dieId.
            asPayload({
                ...quote,
                matchedDie: undefined,
                customer_id: customerId,
                title: title || null,
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
                title={editing ? `إعادة تسعير #${editing.id}` : 'تسعير علبة'}
            />
            <PageHeader
                title={
                    editing
                        ? `إعادة تسعير #${editing.id} — ${editing.name}`
                        : 'تسعير علبة جديدة'
                }
                description={
                    editing
                        ? 'الأسعار الحالية من الكتالوج. لما تأكد، الشغلانة بتتحدث وترجع مسودة عشان يتبعت عرض سعر جديد.'
                        : 'اختار العميل، سعّر العلبة، واكتب نسبة الربح — لما تأكد الطلب بيتسجل كشغلانة (مسودة).'
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
                <Field
                    label="اسم الشغلانة (اختياري)"
                    htmlFor="title"
                    hint="لو فاضي هيتسمى تلقائي، مثال: علبة دواء 9×5×3"
                >
                    <Input
                        id="title"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                    />
                </Field>
            </div>

            <QuickBoxPricingCalculator
                dies={dies}
                papers={papers}
                pastJobs={pastJobs}
                pricingConstants={pricingConstants}
                defaultMarginPercent={defaultMarginPercent}
                initialJob={initial?.job ?? null}
                initialMarginPercent={initial?.marginPercent ?? null}
                onConfirmOrder={confirm}
            />
        </PageBody>
    );
}

CreateBoxJob.layout = {
    breadcrumbs: [
        { title: 'الشغلانات', href: JobController.index() },
        { title: 'تسعير علبة', href: BoxJobController.create() },
    ],
};
