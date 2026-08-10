<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'routine_id',
        'user_id',
        'teacher_id',
        'teacher_name',
        'subject',
        'type',
        'start_date',
        'end_date',
        'days',
        'duration',
        'periods',
        'reason',
        'status',
        'proxy_relevant',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'integer',
        'periods' => 'array',
        'proxy_relevant' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
