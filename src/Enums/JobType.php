<?php

namespace App\Enums;

enum JobType: string
{
    case FullTime = 'Full-Time';
    case PartTime = 'Part-Time';
    case Remote = 'Remote';
    case Hybrid = 'Hybrid';
    case Contract = 'Contract';
    case Internship = 'Internship';
    case Freelance = 'Freelance';

    public function label(): string
    {
        return match($this) {
            self::FullTime => 'Full-Time',
            self::PartTime => 'Part-Time',
            self::Remote => 'Remote',
            self::Hybrid => 'Hybrid',
            self::Contract => 'Contract',
            self::Internship => 'Internship',
            self::Freelance => 'Freelance',
        };
    }
}
