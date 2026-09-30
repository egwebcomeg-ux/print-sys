import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { date } from '@/lib/format';
import { cn } from '@/lib/utils';

type Item = {
    id: string;
    title: string;
    body: string;
    level: 'info' | 'success' | 'warning' | 'error';
    url: string | null;
    read: boolean;
    at: string | null;
};

const dot: Record<Item['level'], string> = {
    info: 'bg-sky-400',
    success: 'bg-emerald-400',
    warning: 'bg-amber-400',
    error: 'bg-red-400',
};

/** Header bell: unread count from shared props, list loaded when opened. */
export function NotificationBell() {
    const { unreadNotifications } = usePage<{ unreadNotifications: number }>()
        .props;
    const [items, setItems] = useState<Item[] | null>(null);

    const load = async () => {
        const res = await fetch(NotificationController.index.url(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = (await res.json()) as { items: Item[] };
            setItems(data.items);
        }
    };

    return (
        <DropdownMenu onOpenChange={(open) => open && void load()}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label="الإشعارات"
                >
                    <Bell className="size-5" />
                    {unreadNotifications > 0 && (
                        <span className="absolute -end-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {unreadNotifications > 9
                                ? '9+'
                                : unreadNotifications}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b border-border px-3 py-2">
                    <span className="text-sm font-semibold">الإشعارات</span>
                    {unreadNotifications > 0 && (
                        <button
                            type="button"
                            className="text-xs text-emerald-400 hover:underline"
                            onClick={() =>
                                router.post(
                                    NotificationController.readAll.url(),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setItems(
                                                (prev) =>
                                                    prev?.map((i) => ({
                                                        ...i,
                                                        read: true,
                                                    })) ?? null,
                                            ),
                                    },
                                )
                            }
                        >
                            علّم الكل مقروء
                        </button>
                    )}
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {items === null && (
                        <p className="p-4 text-center text-sm text-muted-foreground">
                            جاري التحميل...
                        </p>
                    )}
                    {items?.length === 0 && (
                        <p className="p-4 text-center text-sm text-muted-foreground">
                            مفيش إشعارات
                        </p>
                    )}
                    {items?.map((item) => (
                        <a
                            key={item.id}
                            href={NotificationController.open.url(item.id)}
                            className={cn(
                                'flex gap-2 border-b border-border/60 px-3 py-2.5 text-sm hover:bg-accent',
                                item.read && 'opacity-60',
                            )}
                        >
                            <span
                                className={cn(
                                    'mt-1.5 size-2 shrink-0 rounded-full',
                                    item.read
                                        ? 'bg-transparent'
                                        : dot[item.level],
                                )}
                            />
                            <span className="min-w-0">
                                <span className="block font-medium">
                                    {item.title}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {item.body}
                                </span>
                                <span className="block text-[11px] text-muted-foreground">
                                    {date(item.at)}
                                </span>
                            </span>
                        </a>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
