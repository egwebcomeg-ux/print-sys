import { Head, router } from '@inertiajs/react';
import ReportsController from '@/actions/App/Http/Controllers/ReportsController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Input } from '@/components/ui/input';
import { date, egp, num } from '@/lib/format';
import { cn } from '@/lib/utils';

type Summary = {
    jobs: number;
    quotedCount: number;
    quotedValue: number;
    wonCount: number;
    wonValue: number;
    profit: number;
    avgMargin: number | null;
    conversion: number | null;
    invoicedValue: number;
};

type Props = {
    month: string;
    summary: Summary;
    topCustomers: { name: string; jobs: number; value: number }[];
    byType: { label: string; jobs: number; value: number }[];
    waste: {
        press: string;
        jobs: number;
        avgDeviation: number;
        worst: number;
    }[];
    priceChanges: {
        paper: string;
        supplier: string;
        old: number | null;
        new: number;
        changePercent: number | null;
        by: string | null;
        at: string | null;
    }[];
};

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold tabular-nums">
                {value}
            </div>
            {hint && (
                <div className="text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}

export default function Reports({
    month,
    summary,
    topCustomers,
    byType,
    waste,
    priceChanges,
}: Props) {
    return (
        <PageBody>
            <Head title="التقارير" />
            <PageHeader
                title="التقارير"
                description="المبيعات والهوامش في الشهر، أكتر العملاء، الهالك لكل مطبعة، وتغيّرات أسعار الورق"
                actions={
                    <Input
                        type="month"
                        value={month}
                        onChange={(e) =>
                            e.target.value &&
                            router.get(
                                ReportsController.url({
                                    query: { month: e.target.value },
                                }),
                                {},
                                { preserveScroll: true },
                            )
                        }
                        className="w-44"
                        dir="ltr"
                    />
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <Stat
                    label="عروض أسعار اتبعتت"
                    value={num(summary.quotedCount)}
                    hint={egp(summary.quotedValue)}
                />
                <Stat
                    label="شغلانات اتكسبت"
                    value={num(summary.wonCount)}
                    hint={egp(summary.wonValue)}
                />
                <Stat
                    label="نسبة التحويل"
                    value={
                        summary.conversion !== null
                            ? `${summary.conversion}%`
                            : '—'
                    }
                    hint="اتكسبت ÷ اتبعتت"
                />
                <Stat
                    label="متوسط الهامش"
                    value={
                        summary.avgMargin !== null
                            ? `${summary.avgMargin}%`
                            : '—'
                    }
                    hint={`ربح ${egp(summary.profit)}`}
                />
            </div>

            <div className="grid gap-6 *:min-w-0 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 text-base font-semibold">
                        أكتر العملاء (الشغل المكسوب)
                    </h2>
                    <DataTable>
                        <thead>
                            <tr>
                                <Th>العميل</Th>
                                <Th>الشغلانات</Th>
                                <Th>القيمة</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {topCustomers.length === 0 && (
                                <EmptyRow colSpan={3}>
                                    مفيش شغل مكسوب في الشهر ده
                                </EmptyRow>
                            )}
                            {topCustomers.map((c) => (
                                <tr key={c.name}>
                                    <Td className="font-medium">{c.name}</Td>
                                    <Td>{c.jobs}</Td>
                                    <Td>{egp(c.value)}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>

                    {byType.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2 text-sm">
                            {byType.map((t) => (
                                <span
                                    key={t.label}
                                    className="rounded-md border border-border px-3 py-1"
                                >
                                    {t.label}: {t.jobs} · {egp(t.value)}
                                </span>
                            ))}
                        </div>
                    )}
                </section>

                <section>
                    <h2 className="mb-1 text-base font-semibold">
                        الهالك لكل مطبعة (آخر 6 شهور)
                    </h2>
                    <p className="mb-3 text-xs text-muted-foreground">
                        الفرق بين الكمية الفعلية والمطلوبة. بالسالب = نقص عن
                        المطلوب (هالك زيادة عن المحسوب).
                    </p>
                    <DataTable>
                        <thead>
                            <tr>
                                <Th>المطبعة</Th>
                                <Th>شغلانات</Th>
                                <Th>متوسط الفرق</Th>
                                <Th>أسوأ فرق</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {waste.length === 0 && (
                                <EmptyRow colSpan={4}>
                                    مفيش شغلانات خلصت لسه
                                </EmptyRow>
                            )}
                            {waste.map((w) => (
                                <tr key={w.press}>
                                    <Td className="font-medium">{w.press}</Td>
                                    <Td>{w.jobs}</Td>
                                    <Td
                                        dir="ltr"
                                        className={cn(
                                            'text-right',
                                            w.avgDeviation < -3 &&
                                                'text-red-400',
                                        )}
                                    >
                                        {w.avgDeviation > 0 ? '+' : ''}
                                        {w.avgDeviation}%
                                    </Td>
                                    <Td
                                        dir="ltr"
                                        className={cn(
                                            'text-right',
                                            Math.abs(w.worst) > 5 &&
                                                'text-red-400',
                                        )}
                                    >
                                        {w.worst}%
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </section>
            </div>

            <section className="mt-6">
                <h2 className="mb-3 text-base font-semibold">
                    آخر تغيّرات أسعار الورق
                </h2>
                <DataTable>
                    <thead>
                        <tr>
                            <Th>الورق</Th>
                            <Th>المورد</Th>
                            <Th>السعر القديم</Th>
                            <Th>السعر الجديد</Th>
                            <Th>التغيير</Th>
                            <Th>بواسطة</Th>
                            <Th>الوقت</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {priceChanges.length === 0 && (
                            <EmptyRow colSpan={7}>مفيش تغيّرات مسجلة</EmptyRow>
                        )}
                        {priceChanges.map((p, i) => (
                            <tr key={i}>
                                <Td>{p.paper}</Td>
                                <Td>{p.supplier}</Td>
                                <Td>{p.old !== null ? egp(p.old) : 'جديد'}</Td>
                                <Td className="font-medium">{egp(p.new)}</Td>
                                <Td
                                    dir="ltr"
                                    className={cn(
                                        'text-right',
                                        (p.changePercent ?? 0) > 0
                                            ? 'text-red-400'
                                            : 'text-emerald-400',
                                    )}
                                >
                                    {p.changePercent !== null
                                        ? `${p.changePercent > 0 ? '+' : ''}${p.changePercent}%`
                                        : '—'}
                                </Td>
                                <Td>{p.by ?? '—'}</Td>
                                <Td className="text-xs">{date(p.at)}</Td>
                            </tr>
                        ))}
                    </tbody>
                </DataTable>
            </section>
        </PageBody>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'التقارير', href: ReportsController() }],
};
