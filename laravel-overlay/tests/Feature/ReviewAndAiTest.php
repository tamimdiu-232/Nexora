<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Review;
use App\Services\AiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReviewAndAiTest extends TestCase
{
    private function answer(\App\Models\User $owner): ChatMessage
    {
        $s = ChatSession::create(['user_id' => $owner->id, 'title' => 't']);
        ChatMessage::create(['chat_session_id' => $s->id, 'role' => 'user', 'content' => 'Q?']);
        return ChatMessage::create(['chat_session_id' => $s->id, 'role' => 'assistant', 'content' => 'A', 'status' => 'answered']);
    }

    public function test_answers_are_never_verified_automatically(): void
    {
        $a = $this->org(); $d = $this->dept($a); $u = $this->user('officer', $a, $d);
        $msg = $this->answer($u);
        $this->assertSame(0, Review::count());
        $this->actingAs($u)->post('/messages/' . $msg->id . '/review')->assertRedirect();
        $this->assertDatabaseHas('reviews', ['chat_message_id' => $msg->id, 'status' => 'pending', 'reviewer_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.requested']);
    }

    public function test_cannot_submit_someone_elses_answer_for_review(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $owner = $this->user('officer', $a, $d); $other = $this->user('officer', $a, $d);
        $msg = $this->answer($owner);
        $this->actingAs($other)->post('/messages/' . $msg->id . '/review')->assertForbidden();
    }

    public function test_viewer_cannot_submit_or_decide_reviews(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $viewer = $this->user('viewer', $a, $d); $officer = $this->user('officer', $a, $d);
        $review = Review::create(['chat_message_id' => $this->answer($officer)->id, 'requested_by' => $officer->id]);
        $this->actingAs($viewer)->post('/messages/' . $review->chat_message_id . '/review')->assertForbidden();
        $this->post('/reviews/' . $review->id . '/decide', ['status' => 'verified'])->assertForbidden();
        $this->get('/reviews')->assertForbidden();
        $this->assertSame('pending', $review->fresh()->status);
    }

    public function test_reviewer_records_decision_with_identity_timestamp_and_audit(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $officer = $this->user('officer', $a, $d); $reviewer = $this->user('reviewer', $a, $d);
        $review = Review::create(['chat_message_id' => $this->answer($officer)->id, 'requested_by' => $officer->id]);
        $this->actingAs($reviewer)->get('/reviews')->assertOk();
        $this->post('/reviews/' . $review->id . '/decide', ['status' => 'verified', 'comment' => 'Matches page 1'])->assertRedirect();
        $r = $review->fresh();
        $this->assertSame('verified', $r->status);
        $this->assertSame($reviewer->id, $r->reviewer_id);
        $this->assertNotNull($r->decided_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.decided', 'subject_id' => $review->id, 'user_id' => $reviewer->id]);
    }

    public function test_rejection_requires_a_comment_and_reviewer_cannot_review_own_submission(): void
    {
        $a = $this->org(); $d = $this->dept($a);
        $officer = $this->user('officer', $a, $d); $reviewer = $this->user('reviewer', $a, $d);
        $review = Review::create(['chat_message_id' => $this->answer($officer)->id, 'requested_by' => $officer->id]);
        $this->actingAs($reviewer)->post('/reviews/' . $review->id . '/decide', ['status' => 'rejected'])->assertSessionHasErrors('comment');
        $own = Review::create(['chat_message_id' => $this->answer($reviewer)->id, 'requested_by' => $reviewer->id]);
        $this->post('/reviews/' . $own->id . '/decide', ['status' => 'verified'])->assertForbidden();
    }

    public function test_reviewer_from_another_organization_cannot_decide(): void
    {
        $a = $this->org(); $b = $this->org(); $d = $this->dept($a);
        $officer = $this->user('officer', $a, $d); $outsider = $this->user('reviewer', $b, $this->dept($b));
        $review = Review::create(['chat_message_id' => $this->answer($officer)->id, 'requested_by' => $officer->id]);
        $this->actingAs($outsider)->post('/reviews/' . $review->id . '/decide', ['status' => 'verified'])->assertForbidden();
        $this->get('/reviews')->assertOk()->assertDontSee('Q?', false);
    }

    public function test_ai_client_always_sends_service_token(): void
    {
        Http::fake(['ai.test/*' => Http::response(['status' => 'ok'])]);
        app(AiClient::class)->health();
        Http::assertSent(fn ($r) => $r->hasHeader('X-Service-Token', 'test-token') && $r->url() === 'http://ai.test/health');
    }

    public function test_ai_401_is_reported_as_auth_failure(): void
    {
        Http::fake(['ai.test/*' => Http::response(['detail' => 'Invalid service credentials'], 401)]);
        try { app(AiClient::class)->qa('x', ['a']); $this->fail('expected exception'); }
        catch (\App\Exceptions\AiServiceException $e) { $this->assertSame('ai_auth_failed', $e->errorCode); }
    }

    public function test_summary_is_cached_and_scoped_to_the_single_document(): void
    {
        $a = $this->org(); $d = $this->dept($a); $doc = $this->doc($a, $d);
        Http::fake(['ai.test/v1/summarize' => Http::response(['short' => [['text' => 'S', 'page' => 1]], 'detailed' => [], 'key_points' => [], 'dates' => [], 'amounts' => [], 'action_items' => []])]);
        $u = $this->user('viewer', $a, $d);
        $this->actingAs($u)->post('/documents/' . $doc->uuid . '/summarize')->assertRedirect();
        $this->assertNotNull($doc->metadata()->first()->summary);
        Http::assertSent(fn ($r) => $r['authorized_document_ids'] === [$doc->uuid] && $r['document_id'] === $doc->uuid);
        $this->get('/documents/' . $doc->uuid)->assertOk()->assertSee('AI-generated, not verified');
    }

    public function test_related_documents_are_revalidated_against_user_scope(): void
    {
        $a = $this->org(); $b = $this->org(); $d = $this->dept($a);
        $doc = $this->doc($a, $d); $ok = $this->doc($a, null, ['title' => 'RelatedOK']); $leak = $this->doc($b, null, ['title' => 'RelatedLeak']);
        Http::fake(['ai.test/v1/related' => Http::response(['results' => [
            ['document_id' => $ok->uuid, 'title' => 'x', 'category' => 'Other', 'score' => 0.5, 'explanation' => null, 'snippet' => 's'],
            ['document_id' => $leak->uuid, 'title' => 'x', 'category' => 'Other', 'score' => 0.4, 'explanation' => null, 'snippet' => 's']]])]);
        $this->actingAs($this->user('viewer', $a, $d))->get('/documents/' . $doc->uuid . '/related')->assertSee('RelatedOK')->assertDontSee('RelatedLeak');
        Http::assertSent(fn ($r) => !in_array($leak->uuid, $r['authorized_document_ids'], true));
    }

    public function test_dashboard_counts_only_visible_documents(): void
    {
        $a = $this->org(); $b = $this->org(); $d = $this->dept($a);
        $this->doc($a, $d); $this->doc($a, $d); $this->doc($b);
        Http::fake(['ai.test/*' => Http::response(['status' => 'ok', 'documents_indexed' => 3])]);
        $this->actingAs($this->user('viewer', $a, $d))->get('/dashboard')->assertOk()->assertViewHas('total', 2);
    }
}
