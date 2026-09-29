// Latin digits everywhere for money and counts (matches the calculators' inputs).
const egpFormatter = new Intl.NumberFormat('ar-EG', {
    numberingSystem: 'latn',
    maximumFractionDigits: 2,
});

const numberFormatter = new Intl.NumberFormat('ar-EG', {
    numberingSystem: 'latn',
    maximumFractionDigits: 3,
});

/** "12٬500 ج" */
export function egp(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${egpFormatter.format(Number(value))} ج`;
}

export function num(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return numberFormatter.format(Number(value));
}

export function date(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ar-EG', {
        numberingSystem: 'latn',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
