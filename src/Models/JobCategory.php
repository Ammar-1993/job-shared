<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'job_categories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
    
    // يفضل إضافة العلاقة العكسية للمستقبل
    public function jobVacancies()
    {
        return $this->hasMany(JobVacancy::class, 'jobCategoryId', 'id');
    }
}
