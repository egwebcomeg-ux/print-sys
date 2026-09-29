import type {
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

/** Label + control + validation error, the building block of every form. */
export function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

const controlClass =
    'flex w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30';

/**
 * Native select: plays well with Inertia <Form> (plain form fields) and
 * with RTL, unlike a portal-based custom select.
 */
export function NativeSelect({
    options,
    placeholder,
    className,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement> & {
    options: Option[];
    placeholder?: string;
}) {
    return (
        <select className={cn(controlClass, 'h-9 py-1', className)} {...props}>
            {placeholder !== undefined && (
                <option value="">{placeholder}</option>
            )}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

export function Textarea({
    className,
    ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return (
        <textarea
            className={cn(controlClass, 'min-h-20', className)}
            {...props}
        />
    );
}

/** A two-column responsive grid for form fields. */
export function FormGrid({ children }: { children: ReactNode }) {
    return <div className="grid gap-5 md:grid-cols-2">{children}</div>;
}
