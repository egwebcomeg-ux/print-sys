import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { DataTable, Td, Th } from '@/components/crud/data-table';
import { DeleteButton } from '@/components/crud/delete-button';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type UserRow = { id: number; name: string; email: string; role: string; roleLabel: string };

export default function UsersIndex({ users }: { users: UserRow[] }) {
    return (
        <PageBody>
            <Head title="المستخدمين" />
            <PageHeader
                title="المستخدمين"
                description="التسجيل مقفول — حسابات الموظفين بتتعمل من هنا بس"
                actions={
                    <Button asChild>
                        <Link href={UserController.create()}>
                            <Plus /> مستخدم جديد
                        </Link>
                    </Button>
                }
            />
            <DataTable>
                <thead>
                    <tr>
                        <Th>الاسم</Th>
                        <Th>الإيميل</Th>
                        <Th>الصلاحية</Th>
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {users.map((user) => (
                        <tr key={user.id}>
                            <Td className="font-medium">{user.name}</Td>
                            <Td dir="ltr" className="text-right">{user.email}</Td>
                            <Td>
                                <Badge variant={user.role === 'admin' ? 'default' : 'secondary'}>{user.roleLabel}</Badge>
                            </Td>
                            <Td className="text-left whitespace-nowrap">
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={UserController.edit(user.id)}>
                                        <Pencil className="size-4" />
                                    </Link>
                                </Button>
                                <DeleteButton url={UserController.destroy(user.id).url} />
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </PageBody>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'المستخدمين', href: UserController.index() }],
};
