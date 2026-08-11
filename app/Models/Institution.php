<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'name',
        'short_name',
        'phone',
        'email',
        'address',
        'academic_year',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function teachers(): HasMany
    {
        return $this->hasMany(TeacherProfile::class);
    }

    public function classSections(): HasMany
    {
        return $this->hasMany(ClassSection::class);
    }
}
