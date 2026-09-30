import { Form } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import JobFileController from '@/actions/App/Http/Controllers/Jobs/JobFileController';
import { DeleteButton } from '@/components/crud/delete-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { date } from '@/lib/format';

export type JobFileItem = {
    id: number;
    name: string;
    size: number;
    version: number;
    note: string | null;
    by: string | null;
    at: string | null;
    canDelete: boolean;
};

const ACCEPT =
    '.pdf,.ai,.eps,.psd,.png,.jpg,.jpeg,.svg,.zip,.rar,.cdr,.tif,.tiff';

function fileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Design / artwork archive for a job: upload (multi-file), download, delete. */
export function JobFiles({
    jobId,
    files,
}: {
    jobId: number;
    files: JobFileItem[];
}) {
    return (
        <section className="mt-6">
            <h2 className="mb-3 text-base font-semibold">ملفات التصميم</h2>

            {files.length > 0 && (
                <ul className="mb-3 divide-y divide-border rounded-xl border border-border">
                    {files.map((f) => (
                        <li
                            key={f.id}
                            className="flex items-center gap-3 px-3 py-2 text-sm"
                        >
                            <FileText className="size-4 shrink-0 text-muted-foreground" />
                            <div className="min-w-0 flex-1">
                                <a
                                    href={
                                        JobFileController.download({
                                            job: jobId,
                                            file: f.id,
                                        }).url
                                    }
                                    className="block truncate font-medium hover:text-emerald-400"
                                    dir="ltr"
                                    title={f.name}
                                >
                                    {f.name}
                                </a>
                                <div className="text-xs text-muted-foreground">
                                    {f.version > 1 && (
                                        <span className="text-amber-400">
                                            نسخة {f.version} ·{' '}
                                        </span>
                                    )}
                                    {fileSize(f.size)} · {f.by ?? '—'} ·{' '}
                                    {date(f.at)}
                                    {f.note && <> · {f.note}</>}
                                </div>
                            </div>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                title="تحميل"
                                aria-label="تحميل"
                            >
                                <a
                                    href={
                                        JobFileController.download({
                                            job: jobId,
                                            file: f.id,
                                        }).url
                                    }
                                >
                                    <Download className="size-4" />
                                </a>
                            </Button>
                            {f.canDelete && (
                                <DeleteButton
                                    url={
                                        JobFileController.destroy({
                                            job: jobId,
                                            file: f.id,
                                        }).url
                                    }
                                    confirmText={`مسح ${f.name}؟`}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Form
                {...JobFileController.store.form(jobId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="flex flex-wrap items-start gap-2"
            >
                {({ errors, processing, progress }) => {
                    const fileError = Object.entries(errors).find(([k]) =>
                        k.startsWith('files'),
                    )?.[1];

                    return (
                        <>
                            <Input
                                type="file"
                                name="files[]"
                                multiple
                                accept={ACCEPT}
                                required
                                className="max-w-sm"
                                aria-label="ملفات التصميم"
                            />
                            <Input
                                name="note"
                                placeholder="ملاحظة (مثلاً: بروفة معتمدة)"
                                className="max-w-xs"
                            />
                            <Button disabled={processing} variant="secondary">
                                <Upload />{' '}
                                {processing && progress
                                    ? `${progress.percentage ?? 0}%`
                                    : 'ارفع'}
                            </Button>
                            <InputError
                                message={fileError ?? errors.note}
                                className="w-full"
                            />
                            <p className="w-full text-xs text-muted-foreground">
                                PDF, AI, EPS, PSD, CDR, TIFF, PNG, JPG, SVG, ZIP
                                — لحد 50 ميجا للملف. نفس الاسم = نسخة جديدة.
                            </p>
                        </>
                    );
                }}
            </Form>
        </section>
    );
}
