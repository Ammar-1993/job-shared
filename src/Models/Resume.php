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
        'filename',
        'fileUri',
        'vector_embedding',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'contactDetails' => 'array',
        ];
    }

    /**
     * Smart accessor for skills: handles JSON arrays, comma-separated strings, parenthetical groups, and slashes.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    public function getSkillsAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            $rawList = $value;
        } else {
            $decoded = json_decode((string) $value, true);
            if (is_array($decoded)) {
                $rawList = $decoded;
            } elseif (is_string($decoded) && $decoded !== '') {
                $rawList = [$decoded];
            } else {
                $rawList = [(string) $value];
            }
        }

        $skills = [];
        foreach ($rawList as $rawItem) {
            $rawStr = is_string($rawItem)
                ? $rawItem
                : (is_array($rawItem) || is_object($rawItem) ? json_encode($rawItem) : (string) $rawItem);
            // Temporarily protect content inside parentheses to avoid premature comma splits
            $placeholders = [];
            $protected = preg_replace_callback('/\(([^)]+)\)/', function ($m) use (&$placeholders) {
                $idx = count($placeholders);
                $placeholders[$idx] = $m[1];
                return "___PAREN_{$idx}___";
            }, $rawStr);

            $tokens = preg_split('/[,;\n]+/', $protected);
            foreach ($tokens as $token) {
                $token = trim($token);
                if ($token === '') continue;

                if (preg_match('/___PAREN_(\d+)___/', $token, $pm)) {
                    $idx = (int) $pm[1];
                    $insideParen = $placeholders[$idx] ?? '';
                    $baseName = trim(str_replace($pm[0], '', $token));
                    $baseClean = preg_replace('/\s*\d+(\.\w+)?$/', '', $baseName);
                    if (!empty($baseClean)) $skills[] = $baseClean;
                    if (!empty($baseName) && $baseName !== $baseClean) $skills[] = $baseName;

                    $subTokens = preg_split('/[,;\/]+/', $insideParen);
                    foreach ($subTokens as $st) {
                        $st = trim($st);
                        if (!empty($st)) $skills[] = $st;
                    }
                } else {
                    if (str_contains($token, '/') && !str_contains(strtoupper($token), 'CI/CD')) {
                        $subSlash = explode('/', $token);
                        foreach ($subSlash as $ss) {
                            $ss = trim($ss);
                            if (!empty($ss)) $skills[] = $ss;
                        }
                    } else {
                        $skills[] = $token;
                    }
                }
            }
        }

        if (in_array('JS', $skills) && !in_array('JavaScript', $skills)) {
            $skills[] = 'JavaScript';
        }

        return array_values(array_unique(array_filter($skills)));
    }

    /**
     * Accessor for experience: handles JSON arrays or plain text.
     */
    public function getExperienceAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * Accessor for education: handles JSON arrays or plain text.
     */
    public function getEducationAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * Mutator for skills: automatically serializes PHP array to JSON string for MySQL storage.
     */
    public function setSkillsAttribute($value): void
    {
        $this->attributes['skills'] = is_array($value)
            ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE)
            : $value;
    }

    /**
     * Mutator for experience: automatically serializes PHP array to JSON string for MySQL storage.
     */
    public function setExperienceAttribute($value): void
    {
        $this->attributes['experience'] = is_array($value)
            ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE)
            : $value;
    }

    /**
     * Mutator for education: automatically serializes PHP array to JSON string for MySQL storage.
     */
    public function setEducationAttribute($value): void
    {
        $this->attributes['education'] = is_array($value)
            ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE)
            : $value;
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
