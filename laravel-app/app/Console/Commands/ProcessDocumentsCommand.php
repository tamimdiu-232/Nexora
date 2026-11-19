<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Console\Command;

class ProcessDocumentsCommand extends Command
{
    protected $signature = 'nexora:process {--all : Reprocess all documents instead of only pending/failed}';
    protected $description = 'Process pending documents through the Nexora AI document intelligence pipeline';

    public function handle(DocumentProcessor $processor): int
    {
        $all = $this->option('all');

        $query = Document::query();
        if (!$all) {
            $query->whereIn('processing_status', ['pending', 'failed']);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->info('No documents found for processing.');
            return Command::SUCCESS;
        }

        $this->info("Found {$documents->count()} document(s) to process.");

        $success = 0;
        $failed = 0;

        foreach ($documents as $doc) {
            $this->output->write("Processing [{$doc->uuid}] {$doc->title}... ");
            $ok = $processor->process($doc);
            if ($ok) {
                $this->info("DONE ({$doc->page_count} pages, category: {$doc->category})");
                $success++;
            } else {
                $this->error("FAILED ({$doc->processing_error})");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Completed processing: {$success} succeeded, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
