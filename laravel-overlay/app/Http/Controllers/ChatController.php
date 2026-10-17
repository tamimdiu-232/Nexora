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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $sessions = ChatSession::where('user_id', $u->id)->latest('updated_at')->get();
        $current = null;
        if ($request->filled('session')) {
            $current = ChatSession::findOrFail((int) $request->input('session'));
            Gate::authorize('view', $current); // another user's session id -> 403
            $current->load(['messages.citations.document', 'messages.review']);
        }
        return view('chat.index', ['sessions' => $sessions, 'current' => $current]);
    }

    public function store(Request $request)
    {
        $s = ChatSession::create(['user_id' => $request->user()->id]);
        return redirect()->route('chat.index', ['session' => $s->id]);
    }

    public function destroy(ChatSession $chatSession)
    {
        Gate::authorize('delete', $chatSession);
        $chatSession->delete();
        return redirect()->route('chat.index')->with('success', 'Conversation deleted.');
    }

    public function ask(Request $request, ChatSession $chatSession, AiClient $ai)
    {
        Gate::authorize('ask', $chatSession);
        $u = $request->user();
        $data = $request->validate(['question' => ['required', 'string', 'min:2', 'max:1000']]);

        // 1. Authorized scope is computed on the server BEFORE retrieval. The AI service can only see these ids.
        $docs = Document::visibleTo($u)->active()->completed()->get(['id', 'uuid', 'title'])->keyBy('uuid');

        ChatMessage::create(['chat_session_id' => $chatSession->id, 'role' => 'user', 'content' => $data['question']]);
        if ($chatSession->title === 'New chat') $chatSession->update(['title' => Str::limit($data['question'], 60)]);

        $result = ['answer' => 'I could not find sufficient evidence in the documents you can access to answer this.',
            'status' => 'insufficient_evidence', 'citations' => [], 'generated_by' => null];
        $valid = collect();

        if ($docs->isNotEmpty()) {
            try {
                $res = $ai->qa($data['question'], $docs->keys()->all());
                // 2. Validate citations again: authorized document, existing chunk, matching page.
                $valid = collect($res['citations'] ?? [])->filter(function ($c) use ($docs) {
                    $doc = $docs->get($c['document_id'] ?? '');
                    return $doc && DocumentChunk::where('document_id', $doc->id)->where('chunk_id', $c['chunk_id'] ?? '')
                        ->where('page_number', (int) ($c['page'] ?? 0))->exists();
                })->values();
                if ($valid->isEmpty()) {
                    $result['answer'] = isset($res['warning']) ? 'The answer could not be verified against the sources and was withheld.'
                        : 'No valid, authorized source could be confirmed for an answer.';
                } else {
                    $result = ['answer' => (string) $res['answer'], 'status' => $res['status'] ?? 'answered',
                        'generated_by' => Str::limit(($res['generated_by'] ?? '') . (isset($res['warning']) ? ' (fallback)' : ''), 60, ''), 'citations' => $valid->all()];
                }
            } catch (AiServiceException $e) {
                $result['answer'] = 'The AI service is unavailable: ' . $e->getMessage();
                $result['status'] = 'error';
            }
        }

        $msg = ChatMessage::create(['chat_session_id' => $chatSession->id, 'role' => 'assistant', 'content' => $result['answer'],
            'status' => $result['status'], 'generated_by' => $result['generated_by']]);
        foreach ($valid as $c) {
            Citation::create(['chat_message_id' => $msg->id, 'document_id' => $docs[$c['document_id']]->id, 'chunk_id' => $c['chunk_id'],
                'page_number' => (int) $c['page'], 'snippet' => Str::limit((string) ($c['snippet'] ?? ''), 500), 'score' => $c['score'] ?? null]);
        }
        $chatSession->touch();
        // The question text is intentionally not logged.
        AuditLogger::log('chat.asked', $chatSession, ['status' => $result['status'], 'citations' => $valid->count()]);

        return redirect()->route('chat.index', ['session' => $chatSession->id]);
    }
}
