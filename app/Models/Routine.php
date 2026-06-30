<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Routine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'term_label',
        'status',
        'days',
        'periods',
        'classes',
        'teachers',
        'generation_rules',
        'generated_grid',
        'teacher_schedule',
        'metrics',
    ];

    protected $casts = [
        'days' => 'array',
        'periods' => 'array',
        'classes' => 'array',
        'teachers' => 'array',
        'generation_rules' => 'array',
        'generated_grid' => 'array',
        'teacher_schedule' => 'array',
        'metrics' => 'array',
    ];
}
