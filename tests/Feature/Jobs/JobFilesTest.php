<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_versions_download_and_delete(): void
    {
        Storage::fake('local');
        $job = Job::factory()->create();
        $sales = User::factory()->create();

        $this->actingAs($sales)->post(route('jobs.files.store', $job), [
            'files' => [UploadedFile::fake()->create('artwork.pdf', 200, 'application/pdf'), UploadedFile::fake()->image('mockup.png')],
            'note' => 'بروفة أولى',
        ])->assertRedirect()->assertSessionHasNoErrors();
        // Same name again → version 2, the old one is kept.
        $this->actingAs($sales)->post(route('jobs.files.store', $job), [
            'files' => [UploadedFile::fake()->create('artwork.pdf', 300, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], JobFile::where('original_name', 'artwork.pdf')->orderBy('version')->pluck('version')->all());
        $file = JobFile::where('version', 2)->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith("job-files/{$job->id}/", $file->path);

        // Production can download (they print from it) and sees it on the job page.
        $production = User::factory()->production()->create();
        $this->actingAs($production)->get(route('jobs.files.download', [$job, $file]))
            ->assertOk()->assertDownload('artwork.pdf');
        $this->actingAs($production)->get(route('jobs.show', $job))
            ->assertInertia(fn ($page) => $page->has('job.files', 3)->where('job.files.0.canDelete', false));

        // Only the uploader or an admin may delete.
        $this->actingAs($production)->delete(route('jobs.files.destroy', [$job, $file]))->assertForbidden();
        $this->actingAs($sales)->delete(route('jobs.files.destroy', [$job, $file]))->assertRedirect();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertDatabaseMissing('job_files', ['id' => $file->id]);
    }

    public function test_rejects_disallowed_types_and_foreign_job_files(): void
    {
        Storage::fake('local');
        $job = Job::factory()->create();
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('jobs.files.store', $job), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('files.0');

        $other = Job::factory()->create();
        $file = $other->files()->create(['original_name' => 'x.pdf', 'path' => 'job-files/x.pdf', 'size' => 1, 'version' => 1]);
        $this->actingAs($user)->get(route('jobs.files.download', [$job, $file]))->assertNotFound();
    }

    public function test_guests_cannot_download(): void
    {
        $job = Job::factory()->create();
        $file = $job->files()->create(['original_name' => 'x.pdf', 'path' => 'job-files/x.pdf', 'size' => 1, 'version' => 1]);

        $this->get(route('jobs.files.download', [$job, $file]))->assertRedirect(route('login'));
    }
}
