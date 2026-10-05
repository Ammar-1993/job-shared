<?php

namespace App\Enums;

enum JobType: string
{
    case FullTime = 'Full-Time';
    case Remote = 'Remote';
    case Hybrid = 'Hybrid';
    case Contract = 'Contract';

    public function label(): string
    {
        return match($this) {
            self::FullTime => 'Full-Time',
            self::Remote => 'Remote',
            self::Hybrid => 'Hybrid',
            self::Contract => 'Contract',
        };
    }
}
