import { usePage } from '@inertiajs/react';

/** Pantopack logo (public/images/logo.png) on a white badge so it reads on the dark sidebar. */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-white p-0.5">
                <img
                    src="/images/logo.png"
                    alt=""
                    className="size-full object-contain"
                />
            </div>
            <div className="ms-1 grid flex-1 text-start text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
