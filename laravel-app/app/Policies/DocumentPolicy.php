<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $u): bool { return $u->hasPermission('documents.view'); }
    public function view(User $u, Document $d): bool { return $u->hasPermission('documents.view') && $d->isVisibleTo($u); }
    public function download(User $u, Document $d): bool { return $this->view($u, $d); }
    public function create(User $u): bool { return $u->hasPermission('documents.upload'); }
    public function update(User $u, Document $d): bool { return $u->hasPermission('documents.edit') && $d->canBeModifiedBy($u); }
    public function delete(User $u, Document $d): bool { return $u->hasPermission('documents.delete') && $d->canBeModifiedBy($u); }
    public function reprocess(User $u, Document $d): bool { return $u->hasPermission('documents.reprocess') && $d->canBeModifiedBy($u); }
}
