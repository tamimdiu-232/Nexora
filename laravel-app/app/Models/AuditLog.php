<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'organization_id', 'action', 'subject_type', 'subject_id', 'ip_address', 'metadata'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
