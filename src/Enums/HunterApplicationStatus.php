<?php

namespace App\Enums;

enum HunterApplicationStatus: string
{
    case DRAFT = 'draft';
    case APPLIED = 'applied';
    case INTERVIEWING = 'interviewing';
    case OFFERED = 'offered';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft / Prepared',
            self::APPLIED => 'Applied',
            self::INTERVIEWING => 'Interviewing',
            self::OFFERED => 'Offer Received',
            self::REJECTED => 'Rejected',
            self::WITHDRAWN => 'Withdrawn',
        };
    }

    public function badgeClasses(): string
    {
        return match($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-300',
            self::APPLIED => 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300',
            self::INTERVIEWING => 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300',
            self::OFFERED => 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300',
            self::REJECTED => 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-900/40 dark:text-rose-300',
            self::WITHDRAWN => 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300',
        };
    }
}
