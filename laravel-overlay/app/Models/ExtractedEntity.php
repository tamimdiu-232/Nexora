<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractedEntity extends Model
{
    protected $fillable = ['document_id', 'type', 'value', 'page_number', 'evidence'];
}
