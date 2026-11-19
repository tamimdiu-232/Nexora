<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'department_id',
        'uploaded_by',
        'title',
        'description',
        'category',
        'category_confidence',
        'category_source',
        'tags',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'page_count',
        'processing_status',
        'processing_error',
        'processed_at',
        'archived_at',
    ];

    protected $hidden = [
        'stored_path',
    ];

    protected function casts(): array
    {
        return [
            'category_confidence' => 'float',
            'tags' => 'array',
            'size_bytes' => 'integer',
            'page_count' => 'integer',
            'processed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function ($document) {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(DocumentMetadata::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(ExtractedEntity::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('processing_status', 'completed');
    }

    /**
     * Scope documents visible to the given user.
     * Core Architectural Rule:
     * - Super Admin has NO access to organization documents (matches nothing).
     * - Same organization_id required.
     * - Document with department is visible to that department's members & Org Admins.
     * - Document without department is organization-wide.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user || $user->isSuperAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('organization_id', $user->organization_id)
            ->where(function (Builder $q) use ($user) {
                // If user is Org Admin or Reviewer, they see all documents across departments in their org
                if ($user->isOrgAdmin() || $user->isReviewer()) {
                    return $q;
                }

                // If document has no department, it is org-wide
                $q->whereNull('department_id');

                // If user has a department, they can also see documents in that department
                if ($user->department_id) {
                    $q->orWhere('department_id', $user->department_id);
                }
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Helpers
    |--------------------------------------------------------------------------
    */

    public function isVisibleTo(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if ((int) $this->organization_id !== (int) $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin() || $user->isReviewer()) {
            return true;
        }

        if ($this->department_id === null) {
            return true;
        }

        return (int) $this->department_id === (int) $user->department_id;
    }

    public function canBeModifiedBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if ((int) $this->organization_id !== (int) $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        if ($user->isDeptAdmin()) {
            return (int) $this->department_id === (int) $user->department_id;
        }

        if ($user->isOfficer()) {
            return (int) $this->uploaded_by === (int) $user->id;
        }

        return false;
    }
}
