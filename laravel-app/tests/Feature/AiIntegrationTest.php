<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * REAL Laravel <-> FastAPI test (no Http::fake). Skipped unless AI_INTEGRATION=1.
 * Run it with:  bash scripts/integration-test.sh   (starts the AI service, runs this class, stops it)
 */
class AiIntegrationTest extends TestCase
{
    private function sample(string $file): UploadedFile
    {
        $path = base_path('../sample-documents/' . $file);
        if (!is_file($path)) $this->markTestSkipped('sample-documents folder not found');
        return new UploadedFile($path, $file, 'application/pdf', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!env('AI_INTEGRATION')) $this->markTestSkipped('Set AI_INTEGRATION=1 and run scripts/integration-test.sh');
    }

    public function test_upload_process_search_ask_and_cross_org_isolation(): void
    {
        $a = $this->org(); $d = $this->dept($a); $b = $this->org();
        $officer = $this->user('officer', $a, $d); $outsider = $this->user('org_admin', $b);

        $this->actingAs($officer)->post('/documents', ['file' => $this->sample('01_infrastructure_project_report.pdf'), 'title' => 'Progress Report'])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertSame('completed', $doc->processing_status, (string) $doc->processing_error);
        $this->assertGreaterThan(0, $doc->chunks()->count());
        $this->assertSame('BDT 50,000,000', $doc->metadata->budget);

        $this->get('/search?q=deadline')->assertSee('Progress Report');
        $this->get('/search?q=project+budget&mode=semantic')->assertSee('Progress Report');

        $s = ChatSession::create(['user_id' => $officer->id]);
        $this->post('/chat/' . $s->id . '/ask', ['question' => 'এই project-এর budget কত এবং deadline কবে?']);
        $answer = $s->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertStringContainsString('50,000,000', $answer->content);
        $this->assertGreaterThan(0, $answer->citations()->count());
        $this->assertSame(1, $answer->citations()->first()->page_number);

        $this->post('/documents/' . $doc->uuid . '/summarize')->assertRedirect();
        $this->assertNotNull($doc->metadata()->first()->summary);

        // A user from another organization gets no evidence and cannot open the document.
        $s2 = ChatSession::create(['user_id' => $outsider->id]);
        $this->actingAs($outsider)->post('/chat/' . $s2->id . '/ask', ['question' => 'What is the project budget and deadline?']);
        $this->assertSame('insufficient_evidence', $s2->messages()->where('role', 'assistant')->first()->status);
        $this->get('/documents/' . $doc->uuid)->assertForbidden();
    }

    public function test_corrupted_pdf_fails_gracefully_and_keeps_the_file(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $this->actingAs($this->user('officer', $a, $d))->post('/documents', ['file' => $this->sample('09_corrupted_sample.pdf'), 'title' => 'Broken'])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertSame('failed', $doc->processing_status);
        $this->assertDatabaseHas('processing_jobs', ['document_id' => $doc->id, 'error_code' => 'corrupt_pdf']);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($doc->stored_path));
    }

    public function test_scanned_pdf_goes_through_ocr(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $this->actingAs($this->user('officer', $a, $d))->post('/documents', ['file' => $this->sample('08_scanned_notice.pdf'), 'title' => 'Scan'])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertSame('completed', $doc->processing_status, (string) $doc->processing_error);
        $this->assertSame('ocr', $doc->pages()->first()->extraction_method);
    }
}
