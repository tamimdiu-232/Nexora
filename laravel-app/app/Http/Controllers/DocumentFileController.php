<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Files are only ever streamed through here, after a policy check. */
class DocumentFileController extends Controller
{
    private function path(Document $d): string
    {
        abort_unless(Storage::disk('local')->exists($d->stored_path), 404);
        return Storage::disk('local')->path($d->stored_path);
    }

    public function preview(Document $document)
    {
        Gate::authorize('download', $document);
        AuditLogger::log('document.previewed', $document);
        return response()->file($this->path($document), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="document.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Document $document)
    {
        Gate::authorize('download', $document);
        AuditLogger::log('document.downloaded', $document);
        return response()->download($this->path($document), \Illuminate\Support\Str::slug($document->title) . '.pdf', [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
