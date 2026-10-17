<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Citation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchAndChatTest extends TestCase
{
    public function test_keyword_search_never_returns_other_org_or_department_documents(): void
    {
        $a = $this->org(); $b = $this->org(); $d1 = $this->dept($a); $d2 = $this->dept($a);
        $this->doc($a, $d1, ['title' => 'MineDoc']);
        $this->doc($a, $d2, ['title' => 'OtherDeptDoc']);
        $this->doc($b, null, ['title' => 'OtherOrgDoc']);
        $this->actingAs($this->user('viewer', $a, $d1))->get('/search?q=BDT')
            ->assertSee('MineDoc')->assertDontSee('OtherDeptDoc')->assertDontSee('OtherOrgDoc');
    }

    public function test_archived_and_unprocessed_documents_are_not_searchable(): void
    {
        $a = $this->org();
        $this->doc($a, null, ['title' => 'ArchivedDoc', 'archived_at' => now()]);
        $this->doc($a, null, ['title' => 'PendingDoc', 'processing_status' => 'pending']);
        $this->actingAs($this->user('org_admin', $a))->get('/search?q=BDT')->assertDontSee('ArchivedDoc')->assertDontSee('PendingDoc');
    }

    public function test_semantic_search_sends_only_authorized_ids_and_drops_forged_hits(): void
    {
        $a = $this->org(); $b = $this->org(); $d = $this->dept($a);
        $mine = $this->doc($a, $d, ['title' => 'MineDoc']);
        $foreign = $this->doc($b, null, ['title' => 'ForeignDoc']);
        Http::fake(['ai.test/v1/search' => Http::response(['results' => [
            ['document_id' => $mine->uuid, 'chunk_id' => 'c', 'page' => 1, 'title' => 'x', 'snippet' => 'ok snippet', 'score' => 0.9],
            ['document_id' => $foreign->uuid, 'chunk_id' => 'c', 'page' => 1, 'title' => 'x', 'snippet' => 'LEAKED', 'score' => 0.8],
        ]])]);
        $this->actingAs($this->user('viewer', $a, $d))->get('/search?q=budget&mode=semantic')
            ->assertSee('MineDoc')->assertDontSee('ForeignDoc')->assertDontSee('LEAKED');
        Http::assertSent(function ($r) use ($mine, $foreign) {
            $ids = $r['authorized_document_ids'];
            return $ids === [$mine->uuid] && !in_array($foreign->uuid, $ids, true);
        });
    }

    public function test_chat_sends_only_authorized_documents_to_ai(): void
    {
        $a = $this->org(); $b = $this->org(); $d1 = $this->dept($a); $d2 = $this->dept($a);
        $mine = $this->doc($a, $d1); $this->doc($a, $d2); $this->doc($b);
        Http::fake(['ai.test/v1/qa' => Http::response(['answer' => '', 'status' => 'insufficient_evidence', 'citations' => []])]);
        $u = $this->user('officer', $a, $d1); $s = ChatSession::create(['user_id' => $u->id]);
        $this->actingAs($u)->post('/chat/' . $s->id . '/ask', ['question' => 'What is the budget?'])->assertRedirect();
        Http::assertSent(fn ($r) => $r['authorized_document_ids'] === [$mine->uuid]);
    }

    public function test_valid_citation_is_stored_and_shown_with_page_link(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $doc = $this->doc($a, $d, ['title' => 'Bridge Report']);
        Http::fake(['ai.test/v1/qa' => Http::response(['status' => 'answered', 'generated_by' => 'extractive-baseline',
            'answer' => 'The budget is BDT 50,000,000. [1]', 'citations' => [
                ['document_id' => $doc->uuid, 'chunk_id' => $doc->uuid . ':p1:c0', 'page' => 1, 'snippet' => 'budget is BDT 50,000,000', 'score' => 0.5]]])]);
        $u = $this->user('viewer', $a, $d); $s = ChatSession::create(['user_id' => $u->id]);
        $this->actingAs($u)->post('/chat/' . $s->id . '/ask', ['question' => 'budget?']);
        $this->assertDatabaseHas('citations', ['document_id' => $doc->id, 'page_number' => 1]);
        $this->get('/chat?session=' . $s->id)->assertSee('Bridge Report')->assertSee('page=1', false)->assertSee('not verified');
        $this->assertDatabaseHas('audit_logs', ['action' => 'chat.asked']);
        $this->assertStringNotContainsString('budget?', \App\Models\AuditLog::where('action', 'chat.asked')->first()->metadata ? json_encode(\App\Models\AuditLog::where('action', 'chat.asked')->first()->metadata) : '');
    }

    public function test_forged_citations_are_dropped(): void
    {
        $a = $this->org(); $b = $this->org(); $d = $this->dept($a);
        $mine = $this->doc($a, $d); $foreign = $this->doc($b);
        $forged = [
            ['document_id' => $foreign->uuid, 'chunk_id' => $foreign->uuid . ':p1:c0', 'page' => 1, 'snippet' => 'SECRET', 'score' => 1],   // unauthorized document
            ['document_id' => $mine->uuid, 'chunk_id' => $mine->uuid . ':p1:c0', 'page' => 7, 'snippet' => 'wrong page', 'score' => 1],      // page that does not exist
            ['document_id' => $mine->uuid, 'chunk_id' => 'made-up-chunk', 'page' => 1, 'snippet' => 'invented', 'score' => 1],               // chunk that does not exist
        ];
        Http::fake(['ai.test/v1/qa' => Http::response(['status' => 'answered', 'answer' => 'Fabricated answer', 'citations' => $forged])]);
        $u = $this->user('viewer', $a, $d); $s = ChatSession::create(['user_id' => $u->id]);
        $this->actingAs($u)->post('/chat/' . $s->id . '/ask', ['question' => 'anything?']);
        $this->assertSame(0, Citation::count());
        $msg = $s->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('insufficient_evidence', $msg->status);
        $this->assertStringNotContainsString('Fabricated', $msg->content);
    }

    public function test_user_without_visible_documents_gets_insufficient_evidence_without_calling_ai(): void
    {
        Http::fake();
        $a = $this->org(); $u = $this->user('viewer', $a, $this->dept($a)); $s = ChatSession::create(['user_id' => $u->id]);
        $this->actingAs($u)->post('/chat/' . $s->id . '/ask', ['question' => 'hello there']);
        Http::assertNothingSent();
        $this->assertSame('insufficient_evidence', $s->messages()->where('role', 'assistant')->first()->status);
    }

    public function test_ai_outage_is_reported_gracefully(): void
    {
        $a = $this->org(); $d = $this->dept($a); $this->doc($a, $d);
        Http::fake(['ai.test/*' => fn () => throw new ConnectionException('down')]);
        $u = $this->user('viewer', $a, $d); $s = ChatSession::create(['user_id' => $u->id]);
        $this->actingAs($u)->post('/chat/' . $s->id . '/ask', ['question' => 'budget?'])->assertRedirect();
        $this->assertSame('error', $s->messages()->where('role', 'assistant')->first()->status);
    }

    public function test_other_users_chat_history_is_protected(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $owner = $this->user('viewer', $a, $d); $other = $this->user('viewer', $a, $d); $admin = $this->user('org_admin', $a);
        $s = ChatSession::create(['user_id' => $owner->id, 'title' => 'Private question']);
        foreach ([$other, $admin, $this->user('super_admin')] as $u) {
            $this->actingAs($u)->get('/chat?session=' . $s->id)->assertForbidden();
            $this->post('/chat/' . $s->id . '/ask', ['question' => 'peek?'])->assertForbidden();
            $this->delete('/chat/' . $s->id)->assertForbidden();
        }
        $this->actingAs($owner)->get('/chat?session=' . $s->id)->assertOk();
    }

    public function test_question_validation(): void
    {
        $a = $this->org(); $u = $this->user('viewer', $a, $this->dept($a)); $s = ChatSession::create(['user_id' => $u->id]);
        $this->actingAs($u)->post('/chat/' . $s->id . '/ask', ['question' => str_repeat('x', 1001)])->assertSessionHasErrors('question');
    }
}
