<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $fillable = ['document_id', 'chunk_id', 'page_number', 'chunk_index', 'char_count'];
}
