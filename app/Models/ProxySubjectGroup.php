<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxySubjectGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'name',
        'subjects',
        'sort_order',
    ];

    protected $casts = [
        'subjects' => 'array',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
