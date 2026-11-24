<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatCitationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;
    private Document $docA;
    private Document $docB;
    private DocumentChunk $chunkA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Ministry Alpha', 'code' => 'ALPHA', 'is_active' => true]);
        $this->orgB = Organization::create(['name' => 'Ministry Beta', 'code' => 'BETA', 'is_active' => true]);

        $this->userA = User::create([
            'name' => 'Officer Alpha',
            'email' => 'officer@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgA->id,
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'name' => 'Officer Beta',
            'email' => 'officer@beta.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgB->id,
            'is_active' => true,
        ]);

        $this->docA = Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'uploaded_by' => $this->userA->id,
            'title' => 'Project Alpha Proposal',
            'original_name' => 'alpha_doc.pdf',
            'stored_path' => 'documents/alpha_doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', 'alpha_doc.pdf'),
            'page_count' => 5,
            'processing_status' => 'completed',
        ]);

        $this->chunkA = DocumentChunk::create([
            'document_id' => $this->docA->id,
            'chunk_id' => $this->docA->uuid . '_c0',
            'page_number' => 1,
            'chunk_index' => 0,
            'char_count' => 75,
        ]);

        $this->docB = Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'uploaded_by' => $this->userB->id,
            'title' => 'Project Beta Classified Doc',
            'original_name' => 'beta_doc.pdf',
            'stored_path' => 'documents/beta_doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', 'beta_doc.pdf'),
            'page_count' => 3,
            'processing_status' => 'completed',
        ]);
    }

    public function test_valid_citation_is_revalidated_and_persisted(): void
    {
        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'title' => 'Budget Query',
        ]);

        Http::fake([
            '*/v1/qa' => Http::response([
                'answer' => 'The total estimated project budget is BDT 50,000,000 [1].',
                'status' => 'answered',
                'conflict_detected' => false,
                'citations' => [
                    [
                        'document_id' => $this->docA->uuid,
                        'chunk_id' => $this->chunkA->chunk_id,
                        'page_number' => 1,
                        'snippet' => 'The total estimated project budget is BDT 50,000,000',
                        'score' => 0.95,
                    ],
                ],
                'verified' => false,
                'generated_by' => 'extractive',
            ], 200),
        ]);

        $response = $this->actingAs($this->userA)->post(route('chat.ask', $session), [
            'question' => 'What is the project budget?',
        ]);

        $response->assertRedirect(route('chat.show', $session));

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'status' => 'answered',
        ]);

        $this->assertDatabaseHas('citations', [
            'document_id' => $this->docA->id,
            'chunk_id' => $this->chunkA->chunk_id,
            'page_number' => 1,
        ]);
    }

    public function test_forged_out_of_scope_document_citation_is_dropped(): void
    {
        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'title' => 'Attack Probe Query',
        ]);

        // Simulated rogue AI output citing out-of-scope document docB from orgB
        Http::fake([
            '*/v1/qa' => Http::response([
                'answer' => 'Leaked data from Org B [1].',
                'status' => 'answered',
                'conflict_detected' => false,
                'citations' => [
                    [
                        'document_id' => $this->docB->uuid,
                        'chunk_id' => 'forged_chunk',
                        'page_number' => 1,
                        'snippet' => 'classified data',
                        'score' => 0.99,
                    ],
                ],
                'verified' => false,
                'generated_by' => 'extractive',
            ], 200),
        ]);

        $response = $this->actingAs($this->userA)->post(route('chat.ask', $session), [
            'question' => 'Tell me org B secrets',
        ]);

        $response->assertRedirect(route('chat.show', $session));

        // Citation should NOT be created for docB
        $this->assertDatabaseMissing('citations', [
            'document_id' => $this->docB->id,
        ]);

        // Status converted to insufficient_evidence because all citations were invalid
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'status' => 'insufficient_evidence',
        ]);
    }

    public function test_forged_chunk_id_on_valid_document_is_rejected(): void
    {
        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'title' => 'Chunk Tamper Query',
        ]);

        Http::fake([
            '*/v1/qa' => Http::response([
                'answer' => 'Fabricated chunk answer [1].',
                'status' => 'answered',
                'conflict_detected' => false,
                'citations' => [
                    [
                        'document_id' => $this->docA->uuid,
                        'chunk_id' => 'non_existent_chunk_id_xyz',
                        'page_number' => 1,
                        'snippet' => 'fabricated snippet',
                        'score' => 0.90,
                    ],
                ],
                'verified' => false,
                'generated_by' => 'extractive',
            ], 200),
        ]);

        $response = $this->actingAs($this->userA)->post(route('chat.ask', $session), [
            'question' => 'Query with fabricated chunk',
        ]);

        $response->assertRedirect(route('chat.show', $session));

        // Forged chunk must not exist in citations
        $this->assertDatabaseMissing('citations', [
            'chunk_id' => 'non_existent_chunk_id_xyz',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'status' => 'insufficient_evidence',
        ]);
    }

    public function test_chat_session_is_strictly_private_to_owner(): void
    {
        $sessionA = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'title' => 'User A Private Chat',
        ]);

        // User B cannot access User A's chat session
        $response = $this->actingAs($this->userB)->get(route('chat.show', $sessionA));
        $response->assertStatus(403);

        // Even posting to it gives 403
        $response = $this->actingAs($this->userB)->post(route('chat.ask', $sessionA), [
            'question' => 'Spying question',
        ]);
        $response->assertStatus(403);
    }
}
