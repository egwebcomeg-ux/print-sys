import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';

type Quote = {
    number: string;
    date: string;
    validUntil: string;
    customer: { name: string; phone: string | null; email: string | null };
    title: string;
    spec: Record<string, string>;
    quantity: number | null;
    unitPrice: number | null;
    paperItems: {
        label: string;
        paper: string;
        size: string;
        sheets: number;
    }[];
    subtotal: number;
    vatPercent: number;
    vat: number;
    total: number;
    notes: string;
    preparedBy: string | null;
};

type Company = {
    name: string;
    address: string;
    phone: string;
    email: string;
    taxId: string;
};

const money = new Intl.NumberFormat('ar-EG', {
    numberingSystem: 'latn',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const int = new Intl.NumberFormat('ar-EG', { numberingSystem: 'latn' });

/**
 * Customer-facing quote. White, print-first layout; "حفظ PDF" uses the
 * browser's print dialog, which shapes Arabic correctly everywhere.
 */
export default function PrintQuote({
    company,
    quote,
}: {
    company: Company;
    quote: Quote;
}) {
    // Force the light theme for this document regardless of the app's dark default.
    useEffect(() => {
        const html = document.documentElement;
        const wasDark = html.classList.contains('dark');
        html.classList.remove('dark');
        html.style.backgroundColor = '#fff';

        return () => {
            if (wasDark) {
                html.classList.add('dark');
            }
            html.style.backgroundColor = '';
        };
    }, []);

    return (
        <div
            dir="rtl"
            className="min-h-screen bg-white text-slate-900 print:bg-white"
        >
            <Head title={`عرض سعر ${quote.number}`} />
            <style>{`@page { size: A4; margin: 14mm; } @media print { .no-print { display: none !important; } }`}</style>

            <div className="no-print flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-3 text-sm">
                <span className="text-slate-600">
                    معاينة عرض السعر — اختار «حفظ كـ PDF» من نافذة الطباعة
                </span>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500"
                >
                    <Printer className="size-4" /> طباعة / حفظ PDF
                </button>
            </div>

            <main className="mx-auto max-w-[190mm] px-6 py-8 print:px-0 print:py-0">
                <header className="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-5">
                    <div>
                        <h1 className="text-2xl font-bold">{company.name}</h1>
                        <div className="mt-1 space-y-0.5 text-sm text-slate-600">
                            {company.address && <div>{company.address}</div>}
                            {company.phone && (
                                <div dir="ltr" className="text-right">
                                    {company.phone}
                                </div>
                            )}
                            {company.email && (
                                <div dir="ltr" className="text-right">
                                    {company.email}
                                </div>
                            )}
                            {company.taxId && (
                                <div>س.ت / ب.ض: {company.taxId}</div>
                            )}
                        </div>
                    </div>
                    <div className="text-left">
                        <div className="text-xl font-bold">عرض سعر</div>
                        <table className="mt-2 text-sm">
                            <tbody>
                                <tr>
                                    <td className="pe-3 text-slate-500">رقم</td>
                                    <td
                                        dir="ltr"
                                        className="text-right font-medium"
                                    >
                                        {quote.number}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="pe-3 text-slate-500">
                                        التاريخ
                                    </td>
                                    <td dir="ltr" className="text-right">
                                        {quote.date}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="pe-3 text-slate-500">
                                        ساري حتى
                                    </td>
                                    <td dir="ltr" className="text-right">
                                        {quote.validUntil}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </header>

                <section className="mt-6 grid grid-cols-2 gap-6 text-sm">
                    <div>
                        <div className="mb-1 text-xs font-semibold text-slate-500">
                            العميل
                        </div>
                        <div className="text-base font-semibold">
                            {quote.customer.name}
                        </div>
                        {quote.customer.phone && (
                            <div
                                dir="ltr"
                                className="text-right text-slate-600"
                            >
                                {quote.customer.phone}
                            </div>
                        )}
                        {quote.customer.email && (
                            <div
                                dir="ltr"
                                className="text-right text-slate-600"
                            >
                                {quote.customer.email}
                            </div>
                        )}
                    </div>
                    <div>
                        <div className="mb-1 text-xs font-semibold text-slate-500">
                            الشغلانة
                        </div>
                        <div className="text-base font-semibold">
                            {quote.title}
                        </div>
                        {Object.entries(quote.spec).map(([label, value]) => (
                            <div key={label} className="text-slate-600">
                                <span className="text-slate-500">
                                    {label}:{' '}
                                </span>
                                {value}
                            </div>
                        ))}
                    </div>
                </section>

                {quote.paperItems.length > 0 && (
                    <table className="mt-6 w-full border-collapse text-sm">
                        <thead>
                            <tr className="bg-slate-100 text-right">
                                <th className="border border-slate-300 px-3 py-2 font-semibold">
                                    البند
                                </th>
                                <th className="border border-slate-300 px-3 py-2 font-semibold">
                                    الورق
                                </th>
                                <th className="border border-slate-300 px-3 py-2 font-semibold">
                                    المقاس
                                </th>
                                <th className="border border-slate-300 px-3 py-2 font-semibold">
                                    عدد الأفرخ
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {quote.paperItems.map((item, i) => (
                                <tr key={i}>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {item.label}
                                    </td>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {item.paper}
                                    </td>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {item.size}
                                    </td>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {int.format(item.sheets)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <table className="mt-6 w-full border-collapse text-sm">
                    <thead>
                        <tr className="bg-slate-100 text-right">
                            <th className="border border-slate-300 px-3 py-2 font-semibold">
                                الوصف
                            </th>
                            <th className="border border-slate-300 px-3 py-2 font-semibold">
                                الكمية
                            </th>
                            <th className="border border-slate-300 px-3 py-2 font-semibold">
                                سعر الوحدة
                            </th>
                            <th className="border border-slate-300 px-3 py-2 font-semibold">
                                الإجمالي
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className="border border-slate-300 px-3 py-2">
                                {quote.title}
                            </td>
                            <td className="border border-slate-300 px-3 py-2">
                                {quote.quantity
                                    ? int.format(quote.quantity)
                                    : '—'}
                            </td>
                            <td className="border border-slate-300 px-3 py-2">
                                {quote.unitPrice !== null
                                    ? `${money.format(quote.unitPrice)} ج`
                                    : '—'}
                            </td>
                            <td className="border border-slate-300 px-3 py-2">
                                {money.format(quote.subtotal)} ج
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td
                                colSpan={3}
                                className="border border-slate-300 px-3 py-2 text-slate-600"
                            >
                                الإجمالي قبل الضريبة
                            </td>
                            <td className="border border-slate-300 px-3 py-2">
                                {money.format(quote.subtotal)} ج
                            </td>
                        </tr>
                        {quote.vatPercent > 0 && (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="border border-slate-300 px-3 py-2 text-slate-600"
                                >
                                    ضريبة القيمة المضافة {quote.vatPercent}%
                                </td>
                                <td className="border border-slate-300 px-3 py-2">
                                    {money.format(quote.vat)} ج
                                </td>
                            </tr>
                        )}
                        <tr className="bg-slate-100 font-bold">
                            <td
                                colSpan={3}
                                className="border border-slate-300 px-3 py-2"
                            >
                                الإجمالي
                            </td>
                            <td className="border border-slate-300 px-3 py-2">
                                {money.format(quote.total)} ج
                            </td>
                        </tr>
                    </tfoot>
                </table>

                {quote.notes && (
                    <section className="mt-6 text-sm text-slate-600">
                        <div className="mb-1 font-semibold text-slate-700">
                            ملاحظات
                        </div>
                        <div className="whitespace-pre-line">{quote.notes}</div>
                    </section>
                )}

                <footer className="mt-10 flex items-end justify-between text-sm text-slate-600">
                    <div>
                        {quote.preparedBy && (
                            <div>أعدّه: {quote.preparedBy}</div>
                        )}
                        <div className="text-xs text-slate-400">
                            عرض السعر ساري حتى {quote.validUntil}
                        </div>
                    </div>
                    <div className="w-48 border-t border-slate-400 pt-1 text-center">
                        توقيع العميل / الاعتماد
                    </div>
                </footer>
            </main>
        </div>
    );
}
