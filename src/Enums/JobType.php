<?php

namespace App\Enums;

enum JobType: string
{
    case FullTime = 'Full-Time';
    case Contract = 'Contract';
    case Remote = 'Remote';
    case Hybrid = 'Hybrid';
}
