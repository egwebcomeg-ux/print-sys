import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** Confirms, then sends a DELETE to `url`. */
export function DeleteButton({
    url,
    confirmText = 'متأكد إنك عايز تمسح ده؟',
    label,
    size = 'sm',
}: {
    url: string;
    confirmText?: string;
    label?: string;
    size?: 'sm' | 'icon';
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size={size}
            className="text-red-400 hover:bg-red-500/10 hover:text-red-300"
            onClick={() => {
                if (window.confirm(confirmText)) {
                    router.delete(url, { preserveScroll: true });
                }
            }}
        >
            <Trash2 className="size-4" />
            {label && <span>{label}</span>}
        </Button>
    );
}
