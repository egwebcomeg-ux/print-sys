import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { JobStatus } from '@/types';

const statusClass: Record<JobStatus, string> = {
    draft: 'bg-slate-500/15 text-slate-300',
    quoted: 'bg-sky-500/15 text-sky-300',
    approved: 'bg-indigo-500/15 text-indigo-300',
    in_production: 'bg-amber-500/15 text-amber-300',
    completed: 'bg-emerald-500/15 text-emerald-300',
    invoiced: 'bg-teal-500/20 text-teal-200',
};

export function JobStatusBadge({
    status,
    label,
}: {
    status: JobStatus;
    label: string;
}) {
    return (
        <Badge className={cn('border-transparent', statusClass[status])}>
            {label}
        </Badge>
    );
}
