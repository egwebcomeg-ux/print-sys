import type { ReactNode, TdHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** Minimal styled table — enough for the catalogue and job lists. */
export function DataTable({ children }: { children: ReactNode }) {
    return (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <table className="w-full text-right text-sm">{children}</table>
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
