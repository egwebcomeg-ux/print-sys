import { Head, Link } from '@inertiajs/react';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Pagination } from '@/components/crud/pagination';
import { date } from '@/lib/format';
import type { Paginated } from '@/types';

type Entry = {
    id: number;
    subjectType: string;
    subjectId: number | null;
    action: string;
    description: string;
    user: string;
    at: string | null;
};

const subjectLabel: Record<string, string> = {
    job: 'شغلانة',
    settings: 'الإعدادات',
};

export default function ActivityLog({
    entries,
}: {
    entries: Paginated<Entry>;
}) {
    return (
        <PageBody>
            <Head title="سجل النشاط" />
            <PageHeader
                title="سجل النشاط"
                description="مين عمل إيه وامتى — الحالات، إعادة التسعير، توزيع المطابع، الفواتير والإعدادات"
            />
            <DataTable>
                <thead>
                    <tr>
                        <Th>الوقت</Th>
                        <Th>المستخدم</Th>
                        <Th>على</Th>
                        <Th>التفاصيل</Th>
                    </tr>
                </thead>
                <tbody>
                    {entries.data.length === 0 && (
                        <EmptyRow colSpan={4}>مفيش نشاط مسجل لسه</EmptyRow>
                    )}
                    {entries.data.map((e) => (
                        <tr key={e.id}>
                            <Td className="text-xs whitespace-nowrap">
                                {date(e.at)}
                            </Td>
                            <Td>{e.user}</Td>
                            <Td>
                                {e.subjectType === 'job' && e.subjectId ? (
                                    <Link
                                        href={JobController.show(e.subjectId)}
                                        className="text-emerald-400 hover:underline"
                                    >
                                        شغلانة #{e.subjectId}
                                    </Link>
                                ) : (
                                    (subjectLabel[e.subjectType] ??
                                    e.subjectType)
                                )}
                            </Td>
                            <Td>{e.description}</Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
            <Pagination page={entries} />
        </PageBody>
    );
}

ActivityLog.layout = {
    breadcrumbs: [{ title: 'سجل النشاط', href: ActivityLogController() }],
};
