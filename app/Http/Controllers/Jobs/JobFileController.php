<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Design file archive per job. Files live on the private "local" disk and are
 * only reachable through the authorised download route.
 */
class JobFileController extends Controller
{
    public function store(Request $request, Job $job): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.JobFile::MAX_KB, 'extensions:'.implode(',', JobFile::EXTENSIONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'files.*.extensions' => 'نوع الملف مش مسموح (المسموح: '.implode('، ', JobFile::EXTENSIONS).')',
            'files.*.max' => 'أقصى حجم للملف 50 ميجا',
        ]);

        $names = [];
        foreach ($request->file('files') as $upload) {
            $name = $upload->getClientOriginalName();
            $version = (int) $job->files()->where('original_name', $name)->max('version') + 1;

            $job->files()->create([
                'user_id' => $request->user()->id,
                'original_name' => $name,
                'path' => $upload->store("job-files/{$job->id}", 'local'),
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'version' => $version,
                'note' => $request->input('note'),
            ]);
            $names[] = $version > 1 ? "{$name} (نسخة {$version})" : $name;
        }

        ActivityLog::record('job', $job->id, 'file_uploaded', 'رفع ملفات: '.implode('، ', $names));
        $this->toast(count($names) === 1 ? 'تم رفع الملف' : 'تم رفع '.count($names).' ملفات');

        return back();
    }

    public function download(Job $job, JobFile $file): StreamedResponse
    {
        abort_unless($file->job_id === $job->id, 404);
        abort_unless(Storage::disk('local')->exists($file->path), 404, 'الملف مش موجود على السيرفر');

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function destroy(Request $request, Job $job, JobFile $file): RedirectResponse
    {
        abort_unless($file->job_id === $job->id, 404);
        $user = $request->user();
        abort_unless($user->isAdmin() || $file->user_id === $user->id, 403);

        Storage::disk('local')->delete($file->path);
        $file->delete();
        ActivityLog::record('job', $job->id, 'file_deleted', "مسح ملف: {$file->original_name} (نسخة {$file->version})");
        $this->toast('تم مسح الملف');

        return back();
    }
}
