<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case PendingAnalysis = 'pending_analysis'; // الحالة الجديدة للذكاء الاصطناعي
}
