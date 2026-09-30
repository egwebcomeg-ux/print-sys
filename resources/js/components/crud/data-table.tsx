import { useLayoutEffect, useRef } from 'react';
import type { ReactNode, TdHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Minimal styled table. Below md it turns into stacked cards (see
 * .responsive-table in app.css); each cell is labelled with its column
 * header automatically, so pages don't have to repeat the labels.
 */
export function DataTable({ children }: { children: ReactNode }) {
    const ref = useRef<HTMLTableElement>(null);

    // Re-label after every render (rows change with filters/pagination).
    useLayoutEffect(() => {
        const table = ref.current;
        if (!table) {
            return;
        }
        const headers = Array.from(table.querySelectorAll('thead th')).map(
            (th) => th.textContent?.trim() ?? '',
        );
        table.querySelectorAll('tbody tr, tfoot tr').forEach((row) => {
            Array.from(row.children).forEach((cell, i) => {
                const label = headers[i];
                if (label && (cell as HTMLTableCellElement).colSpan === 1) {
                    cell.setAttribute('data-label', label);
                } else {
                    cell.removeAttribute('data-label');
                }
            });
        });
    });

    return (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <table
                ref={ref}
                className="responsive-table w-full text-right text-sm"
            >
                {children}
            </table>
        </div>
    );
}

export function Th({
    children,
    className,
}: {
    children?: ReactNode;
    className?: string;
}) {
    return (
        <th
            className={cn(
                'border-b border-border px-4 py-3 text-xs font-medium whitespace-nowrap text-muted-foreground',
                className,
            )}
        >
            {children}
        </th>
    );
}

export function Td({
    children,
    className,
    ...props
}: TdHTMLAttributes<HTMLTableCellElement>) {
    return (
        <td
            className={cn(
                'border-b border-border/60 px-4 py-3 align-middle',
                className,
            )}
            {...props}
        >
            {children}
        </td>
    );
}

export function EmptyRow({
    colSpan,
    children,
}: {
    colSpan: number;
    children: ReactNode;
}) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="px-4 py-10 text-center text-sm text-muted-foreground"
            >
                {children}
            </td>
        </tr>
    );
}
