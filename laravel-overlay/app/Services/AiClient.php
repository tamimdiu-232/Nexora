<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Thin, authenticated client for the FastAPI service. The service never receives file paths or DB access. */
class AiClient
{
    private function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl(rtrim(config('govdoc.ai_url'), '/'))
            ->withHeaders(['X-Service-Token' => (string) config('govdoc.ai_token')])
            ->acceptJson()
            ->timeout($timeout ?? (int) config('govdoc.ai_timeout'));
    }

    private function send(callable $call): array
    {
        try {
            /** @var Response $res */
            $res = $call();
        } catch (ConnectionException $e) {
            throw new AiServiceException('AI service is not reachable. Start it and try again.', 'ai_unavailable');
        }
        if ($res->failed()) {
            $detail = $res->json('detail');
            $code = is_array($detail) ? ($detail['code'] ?? 'ai_error') : ($res->status() === 401 ? 'ai_auth_failed' : 'ai_error');
            $msg = is_array($detail) ? ($detail['message'] ?? 'AI service error') : (is_string($detail) ? $detail : 'AI service error');
            throw new AiServiceException($msg, $code);
        }
        return $res->json() ?? [];
    }

    public function health(): array
    {
        return $this->send(fn () => $this->http(3)->get('/health'));
    }

    public function process(string $uuid, string $title, string $absolutePath): array
    {
        return $this->send(fn () => $this->http()->attach('file', file_get_contents($absolutePath), 'document.pdf')
            ->post('/v1/documents/process', ['document_id' => $uuid, 'title' => $title]));
    }

    public function remove(string $uuid): void
    {
        $this->send(fn () => $this->http(10)->delete('/v1/documents/' . $uuid));
    }

    /** @param string[] $authorizedUuids already filtered by Laravel for this user */
    public function search(string $query, array $authorizedUuids, int $topK = 20): array
    {
        return $this->send(fn () => $this->http()->post('/v1/search', [
            'question' => $query, 'authorized_document_ids' => array_values($authorizedUuids), 'top_k' => $topK,
        ]))['results'] ?? [];
    }

    public function qa(string $question, array $authorizedUuids): array
    {
        return $this->send(fn () => $this->http()->post('/v1/qa', [
            'question' => $question, 'authorized_document_ids' => array_values($authorizedUuids),
        ]));
    }

    public function summarize(string $uuid): array
    {
        return $this->send(fn () => $this->http()->post('/v1/summarize', [
            'document_id' => $uuid, 'authorized_document_ids' => [$uuid],
        ]));
    }

    public function related(string $uuid, array $authorizedUuids, int $topK = 5): array
    {
        return $this->send(fn () => $this->http()->post('/v1/related', [
            'document_id' => $uuid, 'authorized_document_ids' => array_values($authorizedUuids), 'top_k' => $topK,
        ]))['results'] ?? [];
    }
}
