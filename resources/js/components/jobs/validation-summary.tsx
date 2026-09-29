import { AlertCircle } from 'lucide-react';

/** Lists server validation errors that don't map to a visible field. */
export function ValidationSummary({
    errors,
}: {
    errors: Record<string, string>;
}) {
    const messages = [...new Set(Object.values(errors))];

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 flex items-start gap-2 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            <ul className="space-y-1">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    );
}
