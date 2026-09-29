import { router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/Catalog/CustomerController';
import { NativeSelect } from '@/components/crud/field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type CustomerOption = { id: number; name: string; phone: string | null };

/**
 * Customer select with a "عميل جديد" quick-create dialog. The new customer
 * comes back through Inertia flash data and gets selected automatically.
 */
export function CustomerPicker({
    customers,
    value,
    onChange,
    error,
}: {
    customers: CustomerOption[];
    value: number | null;
    onChange: (id: number | null) => void;
    error?: string;
}) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const create = () => {
        router.post(
            CustomerController.store.url(),
            { name, phone, stay: 1 },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (e) => setErrors(e),
                onFlash: (flash) => {
                    const id = Number(flash.createdCustomerId);
                    if (id) {
                        onChange(id);
                        setOpen(false);
                        setName('');
                        setPhone('');
                        setErrors({});
                    }
                },
            },
        );
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="customer_id">العميل</Label>
            <div className="flex gap-2">
                <NativeSelect
                    id="customer_id"
                    options={customers.map((c) => ({
                        value: String(c.id),
                        label: c.phone ? `${c.name} — ${c.phone}` : c.name,
                    }))}
                    placeholder="اختار العميل..."
                    value={value ? String(value) : ''}
                    onChange={(e) =>
                        onChange(e.target.value ? Number(e.target.value) : null)
                    }
                />
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() => setOpen(true)}
                >
                    <UserPlus /> عميل جديد
                </Button>
            </div>
            <InputError message={error} />

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent dir="rtl">
                    <DialogHeader>
                        <DialogTitle>عميل جديد</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="new-customer-name">الاسم</Label>
                            <Input
                                id="new-customer-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="new-customer-phone">التليفون</Label>
                            <Input
                                id="new-customer-phone"
                                dir="ltr"
                                value={phone}
                                onChange={(e) => setPhone(e.target.value)}
                            />
                            <InputError message={errors.phone} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={create}
                            disabled={processing || !name.trim()}
                        >
                            إضافة واختيار
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
