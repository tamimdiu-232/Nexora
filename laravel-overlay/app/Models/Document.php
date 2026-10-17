<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'department_id', 'uploaded_by', 'title', 'description', 'category',
        'category_confidence', 'category_source', 'tags', 'original_name', 'stored_path', 'mime_type',
        'size_bytes', 'sha256', 'page_count', 'processing_status', 'processing_error', 'processed_at', 'archived_at',
    ];
    protected $casts = ['processed_at' => 'datetime', 'archived_at' => 'datetime', 'category_confidence' => 'float'];
    protected $hidden = ['stored_path']; // internal storage path is never serialized

    /** Public identifier in URLs is the UUID, not the sequential id. */
    public function getRouteKeyName(): string { return 'uuid'; }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function metadata(): HasOne { return $this->hasOne(DocumentMetadata::class); }
    public function pages(): HasMany { return $this->hasMany(DocumentPage::class); }
    public function chunks(): HasMany { return $this->hasMany(DocumentChunk::class); }
    public function jobs(): HasMany { return $this->hasMany(ProcessingJob::class); }
    public function entities(): HasMany { return $this->hasMany(ExtractedEntity::class); }

    /** Organization / department / role visibility rule - the single source of truth. */
    public function isVisibleTo(User $u): bool
    {
        if ($u->isSuper()) return true;
        if ($u->organization_id === null || $u->organization_id !== $this->organization_id) return false;
        if ($this->department_id === null || $u->isOrgAdmin()) return true;
        return $u->department_id === $this->department_id;
    }

    /** Same rule expressed as a scoped query. Apply BEFORE search, retrieval or listing. */
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        if ($u->isSuper()) return $q;
        if ($u->organization_id === null) return $q->whereRaw('1 = 0');
        $q->where('documents.organization_id', $u->organization_id);
        if (!$u->isOrgAdmin()) {
            $q->where(function ($w) use ($u) {
                $w->whereNull('documents.department_id');
                if ($u->department_id !== null) $w->orWhere('documents.department_id', $u->department_id);
            });
        }
        return $q;
    }

    public function scopeActive(Builder $q): Builder { return $q->whereNull('documents.archived_at'); }
    public function scopeCompleted(Builder $q): Builder { return $q->where('documents.processing_status', 'completed'); }

    /** Whether the user may modify (edit/delete/reprocess) - narrower than viewing. */
    public function canBeModifiedBy(User $u): bool
    {
        if (!$this->isVisibleTo($u)) return false;
        return match ($u->role) {
            'super_admin', 'org_admin' => true,
            'dept_admin' => $this->department_id !== null && $this->department_id === $u->department_id,
            'officer' => $this->uploaded_by === $u->id,
            default => false,
        };
    }
}
