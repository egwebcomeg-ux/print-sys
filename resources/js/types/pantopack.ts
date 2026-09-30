// Shared app-level types for Pantopack pages. The calculator components keep
// their own exported interfaces (DieCutTool, PaperType, Press, ...) — import
// those from the component files so there's a single definition of each.

export type Option = { value: string; label: string };

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type UserRole = 'sales' | 'production' | 'admin';

export type Abilities = {
    'manage-catalog': boolean;
    'manage-paper-prices': boolean;
    'update-press-backlog': boolean;
    'manage-settings': boolean;
    'manage-users': boolean;
    'manage-customers': boolean;
    'manage-leads': boolean;
    'create-jobs': boolean;
    'advance-sales-status': boolean;
    'run-production': boolean;
    'manage-invoicing': boolean;
    'manage-inventory': boolean;
};

export type Customer = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    credit_limit_egp: string | null;
    notes: string | null;
    jobs_count?: number;
    /** Positive = owed to us (see CustomerBalance). */
    balance?: number;
};

export type JobStatus =
    | 'draft'
    | 'quoted'
    | 'approved'
    | 'in_production'
    | 'completed'
    | 'invoiced';

export type JobType = 'box' | 'manual';
