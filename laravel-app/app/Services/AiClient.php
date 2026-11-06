<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiClient
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('nexora.ai_service.url', 'http://127.0.0.1:8001'), '/');
        $this->token = config('nexora.ai_service.token', 'development-token-secret-123456');
        $this->timeout = (int) config('nexora.ai_service.timeout', 900);
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-Service-Token' => $this->token,
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout);
    }

    public function health(): array
    {
        try {
            // Unauthenticated health endpoint
            $resp = Http::baseUrl($this->baseUrl)->timeout(5)->get('/health');
            if ($resp->successful()) {
                return $resp->json();
            }
            return ['status' => 'degraded', 'error' => $resp->body()];
        } catch (\Exception $e) {
            return ['status' => 'unavailable', 'error' => $e->getMessage()];
        }
    }

    public function processDocument(string $documentId, string $title, string $filePath): array
    {
        @set_time_limit(900);

        if (!file_exists($filePath)) {
            throw new AiServiceException('file_not_found', "Document file not found at {$filePath}");
        }

        try {
            $resp = $this->client()
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post('/v1/documents/process', [
                    'document_id' => $documentId,
                    'title' => $title,
                ]);

            if ($resp->successful()) {
                return $resp->json();
            }

            $this->handleErrorResponse($resp);
        } catch (ConnectionException $e) {
            throw new AiServiceException('ai_unavailable', 'The AI processing service could not be reached.', 0, $e);
        } catch (AiServiceException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AiServiceException('ai_error', $e->getMessage(), 0, $e);
        }

        return [];
    }

    public function deleteDocument(string $documentId): array
    {
        try {
            $resp = $this->client()->delete("/v1/documents/{$documentId}");
            if ($resp->successful()) {
                return $resp->json();
            }
            return ['deleted' => false];
        } catch (\Exception $e) {
            Log::warning("Could not delete document from AI vector index: {$e->getMessage()}");
            return ['deleted' => false, 'error' => $e->getMessage()];
        }
    }

    public function search(string $query, array $authorizedDocumentIds, int $topK = 20): array
    {
        if (empty($authorizedDocumentIds) || trim($query) === '') {
            return ['results' => []];
        }

        try {
            $resp = $this->client()->post('/v1/search', [
                'query' => $query,
                'authorized_document_ids' => array_values($authorizedDocumentIds),
                'top_k' => $topK,
            ]);

            if ($resp->successful()) {
                return $resp->json();
            }

            $this->handleErrorResponse($resp);
        } catch (ConnectionException $e) {
            throw new AiServiceException('ai_unavailable', 'The AI search engine is currently unavailable.', 0, $e);
        }

        return ['results' => []];
    }

    public function askQuestion(string $question, array $authorizedDocumentIds, int $topK = 8): array
    {
        if (empty($authorizedDocumentIds)) {
            return [
                'answer' => 'No documents are available within your access scope.',
                'status' => 'insufficient_evidence',
                'conflict_detected' => false,
                'conflict_details' => null,
                'citations' => [],
                'verified' => false,
                'generated_by' => 'system',
                'warning' => null,
            ];
        }

        try {
            $resp = $this->client()->post('/v1/qa', [
                'question' => $question,
                'authorized_document_ids' => array_values($authorizedDocumentIds),
                'top_k' => $topK,
            ]);

            if ($resp->successful()) {
                return $resp->json();
            }

            $this->handleErrorResponse($resp);
        } catch (ConnectionException $e) {
            throw new AiServiceException('ai_unavailable', 'The AI Question-Answering engine is currently unavailable.', 0, $e);
        }

        return [];
    }

    public function summarize(string $documentId, array $authorizedDocumentIds): array
    {
        try {
            $resp = $this->client()->post('/v1/summarize', [
                'document_id' => $documentId,
                'authorized_document_ids' => array_values($authorizedDocumentIds),
            ]);

            if ($resp->successful()) {
                return $resp->json();
            }

            $this->handleErrorResponse($resp);
        } catch (ConnectionException $e) {
            throw new AiServiceException('ai_unavailable', 'The AI summarization engine is currently unavailable.', 0, $e);
        }

        return [];
    }

    public function related(string $documentId, array $authorizedDocumentIds, int $topK = 5): array
    {
        try {
            $resp = $this->client()->post('/v1/related', [
                'document_id' => $documentId,
                'authorized_document_ids' => array_values($authorizedDocumentIds),
                'top_k' => $topK,
            ]);

            if ($resp->successful()) {
                return $resp->json();
            }

            $this->handleErrorResponse($resp);
        } catch (ConnectionException $e) {
            throw new AiServiceException('ai_unavailable', 'The AI related documents engine is currently unavailable.', 0, $e);
        }

        return [];
    }

    protected function handleErrorResponse($resp): void
    {
        $status = $resp->status();
        if ($status === 401) {
            throw new AiServiceException('ai_auth_failed', 'AI service authentication failed (check AI_SERVICE_TOKEN).');
        }

        $body = $resp->json();
        $detail = $body['detail'] ?? null;

        if (is_array($detail) && isset($detail['code'])) {
            throw new AiServiceException($detail['code'], $detail['message'] ?? 'AI service processing error.');
        }

        $message = is_string($detail) ? $detail : $resp->body();
        throw new AiServiceException("http_{$status}", $message ?: "AI Service error with HTTP status {$status}");
    }
}
