<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }
}
