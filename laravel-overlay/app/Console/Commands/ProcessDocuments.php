<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Console\Command;

class ProcessDocuments extends Command
{
    protected $signature = 'govdoc:process {--all : Re-process every non-archived document (use after an AI service data reset)}';
    protected $description = 'Send pending (or all) documents to the AI service for extraction and indexing';

    public function handle(DocumentProcessor $processor): int
    {
        $q = Document::whereNull('archived_at');
        if (!$this->option('all')) $q->whereIn('processing_status', ['pending', 'failed']);
        $ok = 0; $bad = 0;
        foreach ($q->get() as $doc) {
            $processor->process($doc) ? $ok++ : $bad++;
            $this->line(($doc->processing_status) . '  ' . $doc->title);
        }
        $this->info("Completed: $ok, failed: $bad");
        return self::SUCCESS;
    }
}
