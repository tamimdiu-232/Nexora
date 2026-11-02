<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view the document.
     * Super Admin has NO access to organization document content.
     */
    public function view(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (!$user->hasPermission('documents.view')) {
            return false;
        }

        return $document->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return $user->hasPermission('documents.upload');
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (!$user->hasPermission('documents.edit')) {
            return false;
        }

        return $document->canBeModifiedBy($user);
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (!$user->hasPermission('documents.delete')) {
            return false;
        }

        return $document->canBeModifiedBy($user);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function preview(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function reprocess(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
