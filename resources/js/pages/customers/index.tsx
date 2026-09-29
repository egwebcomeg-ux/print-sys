import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Catalog/CustomerController';
import { DataTable, EmptyRow, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Pagination } from '@/components/crud/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { egp } from '@/lib/format';
import type { Customer, Paginated } from '@/types';

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginated<Customer>;
    filters: { search: string };
}) {
    const can = useCan();

    return (
        <PageBody>
            <Head title="العملاء" />
            <PageHeader
                title="العملاء"
                description="كل العملاء وعدد الشغلانات بتاعة كل واحد"
                actions={
                    can('manage-customers') && (
                        <Button asChild>
                            <Link href={CustomerController.create()}>
                                <Plus /> عميل جديد
                            </Link>
                        </Button>
                    )
                }
            />

            <Form
                {...CustomerController.index.form()}
                className="mb-4 flex max-w-md gap-2"
            >
                <Input
                    name="search"
                    defaultValue={filters.search}
                    placeholder="دور بالاسم أو التليفون"
                />
                <Button type="submit" variant="secondary">
                    <Search />
                </Button>
            </Form>

            <DataTable>
                <thead>
                    <tr>
                        <Th>الاسم</Th>
                        <Th>التليفون</Th>
                        <Th>الإيميل</Th>
                        <Th>حد الائتمان</Th>
                        <Th>الشغلانات</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {customers.data.length === 0 && (
                        <EmptyRow colSpan={6}>مفيش عملاء لسه</EmptyRow>
                    )}
                    {customers.data.map((customer) => (
                        <tr key={customer.id}>
                            <Td className="font-medium">{customer.name}</Td>
                            <Td dir="ltr">{customer.phone ?? '—'}</Td>
                            <Td>{customer.email ?? '—'}</Td>
                            <Td>{egp(customer.credit_limit_egp)}</Td>
                            <Td>{customer.jobs_count}</Td>
                            <Td className="text-left whitespace-nowrap">
                                {can('manage-customers') && (
                                    <>
                                        <Button asChild variant="ghost" size="sm">
                                            <Link
                                                href={CustomerController.edit(
                                                    customer.id,
                                                )}
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <DeleteButton
                                            url={
                                                CustomerController.destroy(
                                                    customer.id,
                                                ).url
                                            }
                                        />
                                    </>
                                )}
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
            <Pagination page={customers} />
        </PageBody>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'العملاء', href: CustomerController.index() }],
};
