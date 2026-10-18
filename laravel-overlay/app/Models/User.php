<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'organization_id', 'department_id', 'role', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function chatSessions(): HasMany { return $this->hasMany(ChatSession::class); }

    public function isSuper(): bool { return $this->role === 'super_admin'; }
    public function isOrgAdmin(): bool { return $this->role === 'org_admin'; }
    public function isDeptAdmin(): bool { return $this->role === 'dept_admin'; }

    public function roleLabel(): string { return config('nexora.roles.' . $this->role, $this->role); }

    public function hasPermission(string $permission): bool
    {
        $perms = config('nexora.permissions.' . $this->role, []);
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /** Roles this user may assign to others. */
    public function assignableRoles(): array
    {
        $all = array_keys(config('nexora.roles'));
        return match ($this->role) {
            'super_admin' => ['org_admin'], // platform admins only create Organization Admins; they run everything else themselves
            'org_admin' => array_values(array_diff($all, ['super_admin'])),
            'dept_admin' => ['officer', 'reviewer', 'viewer'],
            default => [],
        };
    }

    /** Users this actor is allowed to administer (scoped query). */
    public function scopeManageableBy(Builder $q, User $actor): Builder
    {
        if ($actor->isSuper()) return $q->where('role', 'org_admin');
        if ($actor->isOrgAdmin()) return $q->where('organization_id', $actor->organization_id)->where('role', '!=', 'super_admin');
        if ($actor->isDeptAdmin()) return $q->where('department_id', $actor->department_id)->whereIn('role', ['officer', 'reviewer', 'viewer']);
        return $q->whereRaw('1 = 0');
    }
}
