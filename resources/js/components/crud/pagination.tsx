import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span className="text-muted-foreground">
                {page.from}–{page.to} من {page.total}
            </span>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, index) => {
                    // Laravel's labels carry HTML arrows ("&laquo; السابق" / "التالي &raquo;").
                    const label = link.label
                        .replace('&laquo;', '')
                        .replace('&raquo;', '')
                        .trim();

                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'rounded-md border border-border px-3 py-1',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span
                            key={index}
                            className="rounded-md border border-border px-3 py-1 opacity-40"
                        >
                            {label}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}
