<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
            'role' => UserRole::class, // ربط الدور بالـ Enum
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function resumes()
    {
        return $this->hasMany(Resume::class, 'userId', 'id');
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class, 'userId', 'id');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'ownerId', 'id');
    }

    public function savedJobs()
    {
        // تم التأكد من أسماء الأعمدة في الجدول الوسيط saved_jobs
        return $this->belongsToMany(JobVacancy::class, 'saved_jobs', 'userId', 'jobVacancyId')
                    ->withTimestamps();
    }
}
