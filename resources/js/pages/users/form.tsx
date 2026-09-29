import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { Field, FormGrid, NativeSelect } from '@/components/crud/field';
import { PageBody, PageHeader } from '@/components/crud/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

type UserData = { id: number; name: string; email: string; role: string };

export default function UserForm({ user, roles }: { user: UserData | null; roles: Option[] }) {
    const action = user ? UserController.update.form(user.id) : UserController.store.form();

    return (
        <PageBody>
            <Head title={user ? 'تعديل مستخدم' : 'مستخدم جديد'} />
            <PageHeader title={user ? `تعديل: ${user.name}` : 'مستخدم جديد'} />

            <Form {...action} className="max-w-3xl space-y-6">
                {({ errors, processing }) => (
                    <>
                        <FormGrid>
                            <Field label="الاسم" htmlFor="name" error={errors.name}>
                                <Input id="name" name="name" defaultValue={user?.name} required />
                            </Field>
                            <Field label="الإيميل" htmlFor="email" error={errors.email}>
                                <Input id="email" name="email" type="email" dir="ltr" defaultValue={user?.email} required />
                            </Field>
                            <Field
                                label="الصلاحية"
                                htmlFor="role"
                                error={errors.role}
                                hint="مبيعات: عملاء وتسعير وعروض أسعار — إنتاج: المراحل والمطابع — مدير: كل حاجة"
                            >
                                <NativeSelect id="role" name="role" options={roles} defaultValue={user?.role ?? 'sales'} />
                            </Field>
                            <Field
                                label={user ? 'باسورد جديد (اختياري)' : 'الباسورد'}
                                htmlFor="password"
                                error={errors.password}
                            >
                                <Input id="password" name="password" type="password" dir="ltr" autoComplete="new-password" required={!user} />
                            </Field>
                        </FormGrid>
                        <div className="flex gap-2">
                            <Button disabled={processing}>حفظ</Button>
                            <Button asChild variant="ghost">
                                <Link href={UserController.index()}>إلغاء</Link>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'المستخدمين', href: UserController.index() }],
};
