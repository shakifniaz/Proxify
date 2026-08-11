<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyMessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'proxy_run_id',
        'teacher_profile_id',
        'teacher_name',
        'whatsapp_number',
        'status',
        'provider',
        'provider_message_id',
        'send_batch_id',
        'message_body',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function proxyRun(): BelongsTo
    {
        return $this->belongsTo(ProxyRun::class);
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }
}
