<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'organization_id',
        'department_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function requestedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'requested_by');
    }

    public function decidedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Role and Permission Checks
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isOrgAdmin(): bool
    {
        return $this->role === 'org_admin';
    }

    public function isDeptAdmin(): bool
    {
        return $this->role === 'dept_admin';
    }

    public function isOfficer(): bool
    {
        return $this->role === 'officer';
    }

    public function isReviewer(): bool
    {
        return $this->role === 'reviewer';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles, true);
        }
        return $this->role === $roles;
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $permissions = config("nexora.permissions.{$this->role}", []);
        return in_array($permission, $permissions, true);
    }

    public function canAccessOrganization(int $orgId): bool
    {
        // Super Admin has NO access to organization document plane
        if ($this->isSuperAdmin()) {
            return false;
        }
        return (int) $this->organization_id === $orgId;
    }
}
