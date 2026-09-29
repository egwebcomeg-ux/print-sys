import { router } from '@inertiajs/react';

type RequestPayload = Parameters<typeof router.post>[1];

/**
 * The calculators' quote interfaces are plain JSON data but don't declare an
 * index signature, so TypeScript won't accept them as Inertia payloads
 * directly. They are safe to send as-is.
 */
export function asPayload(data: object): RequestPayload {
    return data as RequestPayload;
}
