<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Citation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\AiClient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            abort(403, 'Platform administrators do not have access to organizational chat.');
        }

        $sessions = ChatSession::where('user_id', $user->id)
            ->latest()
            ->get();

        return view('chat.index', [
            'sessions' => $sessions,
            'currentSession' => null,
            'messages' => collect(),
        ]);
    }

    public function show(ChatSession $session)
    {
        $user = Auth::user();
        Gate::authorize('view', $session);

        $sessions = ChatSession::where('user_id', $user->id)
            ->latest()
            ->get();

        $messages = $session->messages()
            ->with(['citations.document', 'review'])
            ->oldest()
            ->get();

        return view('chat.index', [
            'sessions' => $sessions,
            'currentSession' => $session,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        Gate::authorize('create', ChatSession::class);

        $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
        ]);

        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'title' => $request->input('title') ?: 'New Conversation',
        ]);

        return redirect()->route('chat.show', $session);
    }

    public function ask(Request $request, ChatSession $session, AiClient $aiClient)
    {
        $user = Auth::user();
        Gate::authorize('view', $session);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $question = trim($validated['question']);

        // 1. Record user message
        $userMessage = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $question,
            'status' => 'answered',
            'generated_by' => 'user',
        ]);

        // Auto-title conversation on first message
        if ($session->messages()->count() <= 2 && $session->title === 'New Conversation') {
            $session->update([
                'title' => Str::limit($question, 40),
            ]);
        }

        // 2. Compute user's authorized document UUID scope BEFORE retrieval
        $authorizedUuids = Document::visibleTo($user)
            ->active()
            ->completed()
            ->pluck('uuid')
            ->all();

        if (empty($authorizedUuids)) {
            $assistantMessage = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => 'No processed documents are currently accessible within your organization or department to answer this question.',
                'status' => 'insufficient_evidence',
                'generated_by' => 'system',
            ]);

            AuditLogger::log('chat.question_answered', $session, [
                'status' => 'insufficient_evidence',
                'citation_count' => 0,
            ]);

            return redirect()->route('chat.show', $session);
        }

        // 3. Call AI microservice
        try {
            $aiResponse = $aiClient->askQuestion($question, $authorizedUuids, topK: 8);
        } catch (\Exception $e) {
            $assistantMessage = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => 'The AI intelligence service is temporarily unavailable. Please try again shortly.',
                'status' => 'error',
                'generated_by' => 'system',
            ]);

            AuditLogger::log('chat.question_failed', $session, [
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('chat.show', $session);
        }

        $rawCitations = $aiResponse['citations'] ?? [];
        $aiStatus = $aiResponse['status'] ?? 'answered';
        $answerText = $aiResponse['answer'] ?? '';
        $generatedBy = $aiResponse['generated_by'] ?? 'extractive';

        // 4. Re-validate citations in Laravel (verify doc in scope, chunk exists, page matches)
        $validCitations = [];
        $authUuidSet = array_flip($authorizedUuids);

        // Map authorized documents by UUID
        $scopedDocs = Document::whereIn('uuid', array_column($rawCitations, 'document_id'))
            ->get()
            ->keyBy('uuid');

        foreach ($rawCitations as $rawCite) {
            $docUuid = $rawCite['document_id'] ?? null;
            $chunkId = $rawCite['chunk_id'] ?? null;
            $pageNum = (int) ($rawCite['page_number'] ?? 0);

            // Must be in authorized scope
            if (!$docUuid || !isset($authUuidSet[$docUuid]) || !isset($scopedDocs[$docUuid])) {
                continue;
            }

            $doc = $scopedDocs[$docUuid];

            // Verify chunk exists in document_chunks table and page matches
            $chunkExists = DocumentChunk::where('document_id', $doc->id)
                ->where('chunk_id', $chunkId)
                ->where('page_number', $pageNum)
                ->exists();

            if ($chunkExists) {
                $validCitations[] = [
                    'document_id' => $doc->id,
                    'chunk_id' => $chunkId,
                    'page_number' => $pageNum,
                    'snippet' => $rawCite['snippet'] ?? '',
                    'score' => (float) ($rawCite['score'] ?? 0.0),
                ];
            }
        }

        // If AI claims answered, but citations failed re-validation or were forged
        if ($aiStatus === 'answered' && empty($validCitations)) {
            $aiStatus = 'insufficient_evidence';
            $answerText = 'The generated answer could not be verified against authorized source documents and has been withheld.';
        }

        // 5. Save assistant message and valid citations in transaction
        DB::transaction(function () use ($session, $answerText, $aiStatus, $generatedBy, $validCitations) {
            $assistantMsg = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => $answerText,
                'status' => $aiStatus,
                'generated_by' => $generatedBy,
            ]);

            foreach ($validCitations as $cite) {
                Citation::create([
                    'chat_message_id' => $assistantMsg->id,
                    'document_id' => $cite['document_id'],
                    'chunk_id' => $cite['chunk_id'],
                    'page_number' => $cite['page_number'],
                    'snippet' => $cite['snippet'],
                    'score' => $cite['score'],
                ]);
            }
        });

        // Audit log status and citation count, NEVER question text
        AuditLogger::log('chat.question_answered', $session, [
            'status' => $aiStatus,
            'citation_count' => count($validCitations),
        ]);

        return redirect()->route('chat.show', $session);
    }

    public function destroy(ChatSession $session)
    {
        Gate::authorize('delete', $session);

        $session->delete();

        return redirect()->route('chat.index')->with('status', 'Conversation deleted.');
    }
}
