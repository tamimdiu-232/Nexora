<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessingJob extends Model
{
    protected $fillable = ['document_id', 'status', 'attempt', 'error_code', 'error_message', 'started_at', 'finished_at'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];
}
