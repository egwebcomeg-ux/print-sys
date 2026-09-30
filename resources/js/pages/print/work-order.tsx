import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';

type Order = {
    number: string;
    jobId: number;
    name: string;
    customer: string;
    status: string;
    printedAt: string;
    quantity: number | null;
    press: string | null;
    box: {
        type: string;
        dimensions: string;
        paper: string | null;
        sheet: string | null;
        rawSheets: number | null;
        ups: number | null;
        piecesPerBox: number;
        interlocked: boolean;
        printColors: number;
        lamination: string;
        die: string | null;
        dieLocation: string | null;
    } | null;
    paperItems: {
        label: string;
        paper: string;
        size: string;
        sheets: number;
    }[];
    stages: { name: string; done: boolean }[];
};

const int = new Intl.NumberFormat('ar-EG', { numberingSystem: 'latn' });

function Cell({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="border border-slate-300 px-3 py-2">
            <div className="text-[11px] text-slate-500">{label}</div>
            <div className="text-base font-semibold">{children}</div>
        </div>
    );
}

/**
 * أمر الشغل — A4 ticket that travels with the job on the floor. Large type,
 * everything the operator needs, and a QR that opens the job's stage page.
 */
export default function WorkOrder({
    order,
    qrSvg,
}: {
    order: Order;
    qrSvg: string;
}) {
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

    const b = order.box;

    return (
        <div dir="rtl" className="min-h-screen bg-white text-slate-900">
            <Head title={`أمر شغل ${order.number}`} />
            <style>{`@page { size: A4; margin: 12mm; } @media print { .no-print { display: none !important; } }`}</style>

            <div className="no-print flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-3 text-sm">
                <span className="text-slate-600">
                    أمر الشغل — اطبعه ويمشي مع الشغلانة في المصنع
                </span>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500"
                >
                    <Printer className="size-4" /> طباعة
                </button>
            </div>

            <main className="mx-auto max-w-[190mm] px-6 py-6 print:p-0">
                <header className="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-4">
                    <div className="flex items-start gap-4">
                        <img
                            src="/images/logo.png"
                            alt=""
                            className="size-16 object-contain"
                        />
                        <div>
                            <div className="text-sm text-slate-500">
                                أمر شغل
                            </div>
                            <h1 className="text-3xl font-bold" dir="ltr">
                                {order.number}
                            </h1>
                            <div className="mt-1 text-lg font-semibold">
                                {order.name}
                            </div>
                            <div className="text-slate-600">
                                {order.customer}
                            </div>
                        </div>
                    </div>
                    <div className="text-center">
                        <div
                            className="size-32 [&>svg]:size-full"
                            dangerouslySetInnerHTML={{ __html: qrSvg }}
                        />
                        <div className="mt-1 text-[11px] text-slate-500">
                            سكان لتحديث المرحلة
                        </div>
                    </div>
                </header>

                <section className="mt-4 grid grid-cols-3 gap-0">
                    <Cell label="الكمية المطلوبة">
                        {order.quantity ? int.format(order.quantity) : '—'}
                    </Cell>
                    <Cell label="المطبعة">{order.press ?? 'لسه متوزعتش'}</Cell>
                    <Cell label="الحالة">{order.status}</Cell>
                </section>

                {b && (
                    <section className="mt-4 grid grid-cols-3 gap-0">
                        <Cell label="العلبة">{b.type}</Cell>
                        <Cell label="المقاس">{b.dimensions}</Cell>
                        <Cell label="الورق">{b.paper ?? '—'}</Cell>
                        <Cell label="الفرخ">{b.sheet ?? '—'}</Cell>
                        <Cell label="عدد الأفرخ (شامل الهالك)">
                            {b.rawSheets ? int.format(b.rawSheets) : '—'}
                        </Cell>
                        <Cell label="المونتاج">
                            {b.ups ?? '—'}{' '}
                            {b.piecesPerBox > 1 ? 'قطعة' : 'علبة'}/فرخ
                            {b.interlocked ? ' (متداخل)' : ''}
                        </Cell>
                        <Cell label="الطباعة">
                            {b.printColors === 0
                                ? 'سادة'
                                : `${b.printColors} لون`}
                        </Cell>
                        <Cell label="السلوفان">{b.lamination}</Cell>
                        <Cell label="الاسطمبة">
                            {b.die ?? '—'}
                            {b.dieLocation && (
                                <div className="text-sm font-normal text-slate-600">
                                    {b.dieLocation}
                                </div>
                            )}
                        </Cell>
                    </section>
                )}

                {order.paperItems.length > 0 && (
                    <table className="mt-4 w-full border-collapse text-base">
                        <thead>
                            <tr className="bg-slate-100 text-right">
                                <th className="border border-slate-300 px-3 py-2">
                                    البند
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    الورق
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    المقاس
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    الأفرخ
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.paperItems.map((i, idx) => (
                                <tr key={idx}>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {i.label}
                                    </td>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {i.paper}
                                    </td>
                                    <td className="border border-slate-300 px-3 py-2">
                                        {i.size}
                                    </td>
                                    <td className="border border-slate-300 px-3 py-2 font-semibold">
                                        {int.format(i.sheets)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <section className="mt-6">
                    <h2 className="mb-2 text-lg font-bold">مراحل الإنتاج</h2>
                    <table className="w-full border-collapse text-base">
                        <thead>
                            <tr className="bg-slate-100 text-right">
                                <th className="w-10 border border-slate-300 px-3 py-2">
                                    ✓
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    المرحلة
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    المسؤول
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    التاريخ
                                </th>
                                <th className="border border-slate-300 px-3 py-2">
                                    ملاحظات
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {(order.stages.length > 0
                                ? order.stages
                                : [
                                      {
                                          name: 'المراحل بتتعمل لما العميل يوافق',
                                          done: false,
                                      },
                                  ]
                            ).map((s, idx) => (
                                <tr key={idx} className="h-10">
                                    <td className="border border-slate-300 px-3 text-center">
                                        {s.done ? '✓' : ''}
                                    </td>
                                    <td className="border border-slate-300 px-3">
                                        {s.name}
                                    </td>
                                    <td className="border border-slate-300 px-3" />
                                    <td className="border border-slate-300 px-3" />
                                    <td className="border border-slate-300 px-3" />
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="mt-6 grid grid-cols-2 gap-6 text-sm">
                    <div className="border border-slate-300 p-3">
                        <div className="text-slate-500">
                            الكمية الفعلية بعد الهالك
                        </div>
                        <div className="mt-6 border-b border-slate-400" />
                    </div>
                    <div className="border border-slate-300 p-3">
                        <div className="text-slate-500">توقيع مسؤول الإنتاج</div>
                        <div className="mt-6 border-b border-slate-400" />
                    </div>
                </section>

                <footer className="mt-4 text-[11px] text-slate-400">
                    اتطبع {order.printedAt}
                </footer>
            </main>
        </div>
    );
}
