<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'group_name',
        'route',
        'icon',
        'sort_order',
        'required_permissions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
