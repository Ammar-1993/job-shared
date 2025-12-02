<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Resume extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'resumes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'fileName',
        'fileUrl',
        'contactDetails',
        'education',
        'experience',
        'skills',
        'summary',
        'userId',
        // ملاحظة: الحقول البديلة filename/fileUri لا تضاف هنا لأنها ليست في قاعدة البيانات
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            // تحويل الحقول النصية الطويلة إلى مصفوفات تلقائياً
            'education' => 'array',
            'experience' => 'array',
            'skills' => 'array',
            'contactDetails' => 'array', // في حال كانت JSON أيضاً
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class, 'resumeId', 'id');
    }

    // Accessors & Mutators for Backward Compatibility

    public function getFilenameAttribute()
    {
        return $this->attributes['fileName'] ?? null;
    }

    public function setFilenameAttribute($value)
    {
        $this->attributes['fileName'] = $value;
    }

    public function getFileUriAttribute()
    {
        return $this->attributes['fileUrl'] ?? null;
    }

    public function setFileUriAttribute($value)
    {
        $this->attributes['fileUrl'] = $value;
    }
}
