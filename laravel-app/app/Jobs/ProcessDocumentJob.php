<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1; // failures are recorded on the document and retried by users via "Retry processing"
    public int $timeout = 300;

    public function __construct(public int $documentId) {}

    public function handle(DocumentProcessor $processor): void
    {
        $doc = Document::find($this->documentId);
        if ($doc && !$doc->archived_at) $processor->process($doc);
    }
}
