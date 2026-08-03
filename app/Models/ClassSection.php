<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'class_teacher_profile_id',
        'class_name',
        'section_name',
        'join_code',
        'sort_order',
        'subjects',
    ];

    protected $casts = [
        'subjects' => 'array',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'class_teacher_profile_id');
    }
}
