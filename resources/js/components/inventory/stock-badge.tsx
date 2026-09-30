import { Badge } from '@/components/ui/badge';

export type StockRow = {
    id: number;
    label: string;
    paper: string;
    gsm: number;
    size: string;
    quantity: number;
    reorderLevel: number;
    location: string | null;
    low: boolean;
};

export function StockBadge({
    row,
}: {
    row: Pick<StockRow, 'quantity' | 'low'>;
}) {
    if (row.quantity < 0) {
        return <Badge className="bg-red-500/15 text-red-400">عجز</Badge>;
    }

    if (row.low) {
        return (
            <Badge className="bg-amber-500/15 text-amber-400">محتاج طلب</Badge>
        );
    }

    return <Badge className="bg-emerald-500/15 text-emerald-400">متاح</Badge>;
}
