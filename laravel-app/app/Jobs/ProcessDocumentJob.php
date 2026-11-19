<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 3;

    public function __construct(public Document $document)
    {
    }

    public function handle(DocumentProcessor $processor): void
    {
        $processor->process($this->document);
    }
}
