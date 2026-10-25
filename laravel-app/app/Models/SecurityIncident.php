<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncident extends Model
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const STATUSES = ['open', 'investigating', 'resolved'];

    protected $fillable = ['organization_id', 'reported_by', 'title', 'description', 'severity', 'status', 'resolution_note', 'resolved_at'];
    protected $casts = ['resolved_at' => 'datetime'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
}
