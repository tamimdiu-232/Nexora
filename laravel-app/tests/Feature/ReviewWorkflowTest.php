<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $officerA;
    private User $reviewerA1;
    private User $reviewerA2;
    private User $reviewerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org Alpha', 'code' => 'ALPHA', 'is_active' => true]);
        $this->orgB = Organization::create(['name' => 'Org Beta', 'code' => 'BETA', 'is_active' => true]);

        $this->officerA = User::create([
            'name' => 'Officer Alpha',
            'email' => 'officer@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgA->id,
            'is_active' => true,
        ]);

        $this->reviewerA1 = User::create([
            'name' => 'Reviewer One',
            'email' => 'reviewer1@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'reviewer',
            'organization_id' => $this->orgA->id,
            'is_active' => true,
        ]);

        $this->reviewerA2 = User::create([
            'name' => 'Reviewer Two',
            'email' => 'reviewer2@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'reviewer',
            'organization_id' => $this->orgA->id,
            'is_active' => true,
        ]);

        $this->reviewerB = User::create([
            'name' => 'Reviewer Beta',
            'email' => 'reviewer@beta.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'reviewer',
            'organization_id' => $this->orgB->id,
            'is_active' => true,
        ]);
    }

    private function createAssistantMessage(User $owner): ChatMessage
    {
        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $owner->organization_id,
            'user_id' => $owner->id,
            'title' => 'Project Query',
        ]);

        return ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'The project cost is estimated at BDT 100,000,000.',
            'status' => 'answered',
            'generated_by' => 'extractive',
        ]);
    }

    public function test_user_can_submit_ai_answer_for_review(): void
    {
        $message = $this->createAssistantMessage($this->officerA);

        $response = $this->actingAs($this->officerA)->post(route('reviews.submit', $message));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('reviews', [
            'chat_message_id' => $message->id,
            'requested_by' => $this->officerA->id,
            'status' => 'pending',
        ]);
    }

    public function test_reviewer_cannot_decide_own_submission(): void
    {
        // Reviewer 1 creates a message in their own session and submits it
        $message = $this->createAssistantMessage($this->reviewerA1);

        $this->actingAs($this->reviewerA1)->post(route('reviews.submit', $message));

        $review = Review::where('chat_message_id', $message->id)->firstOrFail();

        // Reviewer 1 tries to decide their own review -> MUST BE 403
        $response = $this->actingAs($this->reviewerA1)->post(route('reviews.decide', $review), [
            'status' => 'verified',
        ]);

        $response->assertStatus(403);
        $review->refresh();
        $this->assertEquals('pending', $review->status);
    }

    public function test_different_reviewer_can_approve_review(): void
    {
        $message = $this->createAssistantMessage($this->officerA);
        $this->actingAs($this->officerA)->post(route('reviews.submit', $message));
        $review = Review::where('chat_message_id', $message->id)->firstOrFail();

        // Reviewer A1 approves the submission made by Officer A
        $response = $this->actingAs($this->reviewerA1)->post(route('reviews.decide', $review), [
            'status' => 'verified',
            'comment' => 'Figures verified against project sheet.',
        ]);

        $response->assertRedirect(route('reviews.index'));

        $review->refresh();
        $this->assertEquals('verified', $review->status);
        $this->assertEquals($this->reviewerA1->id, $review->reviewer_id);
        $this->assertNotNull($review->decided_at);
    }

    public function test_rejection_requires_comment(): void
    {
        $message = $this->createAssistantMessage($this->officerA);
        $this->actingAs($this->officerA)->post(route('reviews.submit', $message));
        $review = Review::where('chat_message_id', $message->id)->firstOrFail();

        // Attempt rejection without comment
        $response = $this->actingAs($this->reviewerA1)->post(route('reviews.decide', $review), [
            'status' => 'rejected',
            'comment' => '',
        ]);

        $response->assertSessionHasErrors('comment');
        $review->refresh();
        $this->assertEquals('pending', $review->status);

        // Supply comment -> successfully rejected
        $response2 = $this->actingAs($this->reviewerA1)->post(route('reviews.decide', $review), [
            'status' => 'rejected',
            'comment' => 'The cited figure is from an obsolete memo.',
        ]);

        $response2->assertRedirect(route('reviews.index'));
        $review->refresh();
        $this->assertEquals('rejected', $review->status);
    }

    public function test_reviewer_cannot_decide_review_from_another_org(): void
    {
        $message = $this->createAssistantMessage($this->officerA);
        $this->actingAs($this->officerA)->post(route('reviews.submit', $message));
        $review = Review::where('chat_message_id', $message->id)->firstOrFail();

        // Reviewer from Org B attempts to decide Org A's review -> 403
        $response = $this->actingAs($this->reviewerB)->post(route('reviews.decide', $review), [
            'status' => 'verified',
        ]);

        $response->assertStatus(403);
    }
}
