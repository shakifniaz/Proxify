<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'user_id',
        'name',
        'date',
        'day_label',
        'status',
        'absent_teachers',
        'subject_groups',
        'assignments',
        'adjustments',
        'proxy_generated_grid',
        'proxy_teacher_schedule',
        'metrics',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'absent_teachers' => 'array',
        'subject_groups' => 'array',
        'assignments' => 'array',
        'adjustments' => 'array',
        'proxy_generated_grid' => 'array',
        'proxy_teacher_schedule' => 'array',
        'metrics' => 'array',
        'approved_at' => 'datetime',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
