<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\HunterApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JobApplication extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'job_applications';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'status',
        'aiGeneratedScore',
        'aiGeneratedFeedback',
        'jobVacancyId',
        'userId',
        'resumeId',
        // حقول وضع الباحث الشخصي (Job Hunter Mode)
        'is_personal',
        'hunter_status',
        'applied_channel',
        'applied_at',
        'follow_up_at',
        'contact_info',
        'notes',
        'suggested_subject_line',
        'tailored_key_points',
        'tailored_cover_letter',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'status' => ApplicationStatus::class, // استخدام Enum الصحيح
            'aiGeneratedScore' => 'float', // ضمان التعامل كرقم
            'is_personal' => 'boolean',
            'hunter_status' => HunterApplicationStatus::class,
            'tailored_key_points' => 'array',
            'applied_at' => 'datetime',
            'follow_up_at' => 'date',
        ];
    }

    /**
     * Scope: الطلبات الشخصية في وضع الباحث عن وظائف (Job Hunter)
     */
    public function scopePersonal($query)
    {
        return $query->where('is_personal', true);
    }

    /**
     * Scope: طلبات عملاء المنصة العاديين
     */
    public function scopeClientApplications($query)
    {
        return $query->where('is_personal', false);
    }

    /**
     * Scope: فلترة الطلبات الشخصية بحسب مرحلة التقديم
     */
    public function scopeHunterStatus($query, $status)
    {
        $statusValue = $status instanceof HunterApplicationStatus ? $status->value : $status;
        return $query->where('hunter_status', $statusValue);
    }

    /**
     * هل الطلب شخصي (Job Hunter Mode)؟
     */
    public function isHunter(): bool
    {
        return (bool) $this->is_personal;
    }

    /**
     * تسجيل التقديم الفعلي على الوظيفة
     */
    public function markAsApplied(?\DateTimeInterface $date = null, ?string $channel = null): bool
    {
        $this->hunter_status = HunterApplicationStatus::APPLIED;
        $this->applied_at = $date ?? now();
        if ($channel) {
            $this->applied_channel = $channel;
        }
        return $this->save();
    }

    /**
     * تحديث المرحلة إلى مقابلة شخصية
     */
    public function markAsInterviewing(?string $notes = null): bool
    {
        $this->hunter_status = HunterApplicationStatus::INTERVIEWING;
        if ($notes) {
            $this->appendNotes($notes);
        }
        return $this->save();
    }

    /**
     * تحديث المرحلة إلى استلام عرض عمل (Job Offer)
     */
    public function markAsOffered(?string $notes = null): bool
    {
        $this->hunter_status = HunterApplicationStatus::OFFERED;
        if ($notes) {
            $this->appendNotes($notes);
        }
        return $this->save();
    }

    /**
     * تحديث المرحلة إلى مرفوض
     */
    public function markAsRejected(?string $notes = null): bool
    {
        $this->hunter_status = HunterApplicationStatus::REJECTED;
        if ($notes) {
            $this->appendNotes($notes);
        }
        return $this->save();
    }

    /**
     * تحديث المرحلة إلى منسحب
     */
    public function markAsWithdrawn(?string $notes = null): bool
    {
        $this->hunter_status = HunterApplicationStatus::WITHDRAWN;
        if ($notes) {
            $this->appendNotes($notes);
        }
        return $this->save();
    }

    /**
     * دمج الملاحظات مع التاريخ الزمني
     */
    public function appendNotes(string $newNotes): void
    {
        $timestamp = now()->format('Y-m-d H:i');
        $entry = "[{$timestamp}] {$newNotes}";
        $this->notes = $this->notes ? "{$this->notes}\n{$entry}" : $entry;
    }

    public function jobVacancy()
    {
        return $this->belongsTo(JobVacancy::class, 'jobVacancyId', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class, 'resumeId', 'id');
    }
}

