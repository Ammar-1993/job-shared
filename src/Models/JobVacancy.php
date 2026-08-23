<?php

namespace App\Models;

use App\Enums\JobType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JobVacancy extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'job_vacancies';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'location',
        'salary',
        'type',
        'viewCount',
        'jobCategoryId',
        'companyId',
        'vector_embedding',
        // حقول تتبع المصدر الخارجي (أُضيفت لدعم pipeline الاستيراد)
        'source_platform',
        'source_url',
        'external_id',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'type' => JobType::class,
            'viewCount' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * الوظائف المستوردة تلقائياً من مصادر خارجية (Greenhouse, WeWorkRemotely...)
     * يُميّزها عن الوظائف المنشورة يدوياً عبر لوحة التحكم.
     */
    public function scopeImported($query)
    {
        return $query->whereNotNull('source_platform');
    }

    public function jobCategory()
    {
        return $this->belongsTo(JobCategory::class, 'jobCategoryId', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'companyId', 'id');
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class, 'jobVacancyId', 'id');
    }
}
