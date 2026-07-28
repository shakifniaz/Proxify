<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherLeaveAllowance extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'teacher_id',
        'teacher_name',
        'year',
        'max_leaves',
    ];

    protected $casts = [
        'year' => 'integer',
        'max_leaves' => 'integer',
    ];
}
