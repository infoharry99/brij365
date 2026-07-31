<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskSavedView extends Model
{
    protected $fillable = ['company_id', 'user_id', 'name', 'filters', 'is_default'];
    protected function casts(): array { return ['filters' => 'array', 'is_default' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
