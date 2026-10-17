<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentPage;
use App\Services\DocumentProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadAndProcessingTest extends TestCase
{
    private function officer(): array
    {
        $org = $this->org(); $d = $this->dept($org);
        return [$org, $d, $this->user('officer', $org, $d)];
    }

    public function test_valid_pdf_is_stored_privately_and_processed(): void
    {
        Http::fake(['ai.test/v1/documents/process' => Http::response($this->processResponse())]);
        [$org, $d, $u] = $this->officer();
        $file = UploadedFile::fake()->createWithContent('My Secret Name.pdf', $this->pdfContent());

        $this->actingAs($u)->post('/documents', ['file' => $file, 'title' => 'Budget 2026'])->assertRedirect();

        $doc = Document::firstOrFail();
        $this->assertSame('completed', $doc->processing_status);
        $this->assertSame('Project Report', $doc->category);
        $this->assertStringNotContainsString('Secret', $doc->stored_path); // generated name, not the user's
        $this->assertTrue(Storage::disk('local')->exists($doc->stored_path));
        $this->assertSame($d->id, $doc->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded', 'subject_id' => $doc->id]);
        $this->assertDatabaseHas('document_metadata', ['document_id' => $doc->id, 'reference_number' => 'REF-1']);
        $this->assertArrayNotHasKey('stored_path', $doc->toArray()); // never serialized
        Http::assertSent(fn ($r) => $r->hasHeader('X-Service-Token', 'test-token'));
    }

    public function test_non_pdf_and_fake_pdf_are_rejected(): void
    {
        Http::fake();
        [, , $u] = $this->officer();
        $this->actingAs($u);
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'title' => 'T'])->assertSessionHasErrors('file');
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.pdf', 'this is not a pdf at all'), 'title' => 'T'])->assertSessionHasErrors('file');
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.php', '<?php echo 1;'), 'title' => 'T'])->assertSessionHasErrors('file');
        $this->assertSame(0, Document::count());
        Http::assertNothingSent();
    }

    public function test_oversized_upload_is_rejected(): void
    {
        [, , $u] = $this->officer();
        $big = UploadedFile::fake()->create('big.pdf', config('govdoc.max_upload_kb') + 10, 'application/pdf');
        $this->actingAs($u)->post('/documents', ['file' => $big, 'title' => 'T'])->assertSessionHasErrors('file');
        $this->assertSame(0, Document::count());
    }

    public function test_officer_cannot_upload_into_another_department(): void
    {
        Http::fake(['ai.test/*' => Http::response($this->processResponse())]);
        [$org, $d, $u] = $this->officer();
        $other = $this->dept($org);
        $this->actingAs($u)->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.pdf', $this->pdfContent()),
            'title' => 'T', 'department_id' => $other->id]);
        $this->assertSame($d->id, Document::firstOrFail()->department_id);
    }

    public function test_ai_service_down_marks_failed_without_losing_the_file(): void
    {
        Http::fake(['ai.test/*' => fn () => throw new ConnectionException('refused')]);
        [, , $u] = $this->officer();
        $this->actingAs($u)->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.pdf', $this->pdfContent()), 'title' => 'T'])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertSame('failed', $doc->processing_status);
        $this->assertTrue(Storage::disk('local')->exists($doc->stored_path));
        $this->assertDatabaseHas('processing_jobs', ['document_id' => $doc->id, 'status' => 'failed', 'error_code' => 'ai_unavailable']);
    }

    public function test_corrupt_pdf_error_from_ai_is_recorded_and_retry_works(): void
    {
        Http::fake(['ai.test/v1/documents/process' => Http::sequence()
            ->push(['detail' => ['code' => 'corrupt_pdf', 'message' => 'Cannot read PDF']], 422)
            ->push($this->processResponse())]);
        $org = $this->org(); $d = $this->dept($org); $u = $this->user('officer', $org, $d);
        $doc = $this->doc($org, $d, ['uploaded_by' => $u->id, 'processing_status' => 'pending']);
        $this->assertFalse(app(DocumentProcessor::class)->process($doc));
        $this->assertSame('failed', $doc->fresh()->processing_status);
        $this->assertDatabaseHas('processing_jobs', ['document_id' => $doc->id, 'error_code' => 'corrupt_pdf']);

        $this->actingAs($u)->post('/documents/' . $doc->uuid . '/reprocess')->assertRedirect();
        $this->assertSame('completed', $doc->fresh()->processing_status);
        $this->assertSame(2, $doc->jobs()->count());
    }

    public function test_reprocessing_does_not_duplicate_pages_or_chunks(): void
    {
        Http::fake(['ai.test/v1/documents/process' => Http::response($this->processResponse())]);
        $org = $this->org();
        $doc = $this->doc($org);
        $p = app(DocumentProcessor::class);
        $p->process($doc); $p->process($doc);
        $this->assertSame(1, DocumentPage::where('document_id', $doc->id)->count());
        $this->assertSame(1, DocumentChunk::where('document_id', $doc->id)->count());
    }

    public function test_ai_never_overwrites_a_manual_category(): void
    {
        Http::fake(['ai.test/v1/documents/process' => Http::response($this->processResponse())]);
        $doc = $this->doc($this->org(), null, ['category' => 'Circular', 'category_source' => 'manual']);
        app(DocumentProcessor::class)->process($doc);
        $this->assertSame('Circular', $doc->fresh()->category);
    }

    public function test_delete_removes_from_listing_and_index_and_is_audited(): void
    {
        Http::fake(['ai.test/*' => Http::response(['deleted' => 'x'])]);
        $org = $this->org(); $d = $this->dept($org); $admin = $this->user('dept_admin', $org, $d);
        $doc = $this->doc($org, $d);
        $this->actingAs($admin)->delete('/documents/' . $doc->uuid)->assertRedirect('/documents');
        $this->assertSoftDeleted('documents', ['id' => $doc->id]);
        $this->get('/documents/' . $doc->uuid)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.deleted', 'subject_id' => $doc->id]);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), $doc->uuid));
    }

    public function test_download_and_preview_are_streamed_and_audited(): void
    {
        $org = $this->org(); $d = $this->dept($org); $u = $this->user('viewer', $org, $d);
        $doc = $this->doc($org, $d);
        $this->actingAs($u)->get('/documents/' . $doc->uuid . '/preview')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/documents/' . $doc->uuid . '/download')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.downloaded', 'subject_id' => $doc->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.previewed', 'subject_id' => $doc->id]);
    }

    public function test_audit_logger_strips_secret_keys(): void
    {
        $log = \App\Services\AuditLogger::log('x.test', null, ['password' => 'p', 'api_key' => 'k', 'safe' => 'ok'], $this->user('viewer', $this->org()));
        $this->assertSame(['safe' => 'ok'], $log->metadata);
    }
}
