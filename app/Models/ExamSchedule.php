<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'routine_id',
        'user_id',
        'name',
        'subtitle',
        'start_date',
        'end_date',
        'status',
        'halls',
        'time_slots',
        'class_options',
        'subject_options',
        'invigilator_options',
        'exam_grid',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'halls' => 'array',
        'time_slots' => 'array',
        'class_options' => 'array',
        'subject_options' => 'array',
        'invigilator_options' => 'array',
        'exam_grid' => 'array',
    ];
}
