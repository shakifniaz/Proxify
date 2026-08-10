<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'user_id',
        'board',
        'title',
        'message',
        'urgency',
        'visibility',
        'acknowledged_by',
        'read_count',
    ];

    protected $casts = [
        'acknowledged_by' => 'array',
        'read_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
