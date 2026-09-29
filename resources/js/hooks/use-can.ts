import { usePage } from '@inertiajs/react';
import type { Abilities, Auth } from '@/types';

/**
 * Role-based UI gating, mirroring App\Support\Abilities on the server.
 * The server still enforces every ability — this only hides buttons.
 */
export function useCan(): (ability: keyof Abilities) => boolean {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (ability) => Boolean(auth.can?.[ability]);
}
