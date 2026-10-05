<?php

namespace App\Support;

class JobFilter
{
    /**
     * Non-technical role keywords that must NEVER be imported for a software engineer / tech profile.
     * Evaluated against the lowercased job title.
     *
     * @var string[]
     */
    protected static array $nonTechnicalTitles = [
        // Customer Service & Support
        'customer support', 'customer service', 'support specialist', 'support representative',
        'customer success', 'help desk', 'helpdesk', 'call center', 'client care',
        
        // Sales & Business Development
        'sales manager', 'sales executive', 'sales representative', 'account executive',
        'business development', 'bdr', 'sdr', 'sales lead', 'commercial manager',
        
        // Marketing & Growth
        'marketing manager', 'marketing specialist', 'digital marketing', 'seo specialist',
        'content writer', 'copywriter', 'social media', 'growth marketer', 'event manager',
        'public relations', 'brand manager',
        
        // HR & Talent Acquisition
        'recruiter', 'talent acquisition', 'human resources', 'hr generalist', 'hr manager',
        'people partner', 'hr business partner', 'payroll specialist', 'sourcer', 'talent partner',
        'people operations',
        
        // Finance, Accounting & Audit
        'accountant', 'accounting', 'bookkeeper', 'financial analyst', 'tax specialist',
        'auditor', 'audit', 'finance manager', 'billing specialist', 'risk analyst', 'risk strategy',
        'controller', 'treasury',
        
        // Legal & Compliance
        'legal counsel', 'paralegal', 'attorney', 'general counsel', 'lawyer', 'compliance manager',
        'compliance specialist',
        
        // Administrative & Operations
        'administrative assistant', 'office manager', 'receptionist', 'executive assistant',
        'operations manager', 'operations specialist', 'business operations', 'chief of staff',
        'warehouse', 'driver', 'courier', 'store manager', 'cashier', 'logistics coordinator',
        
        // Design & Creative (non-engineering)
        'product designer', 'ux designer', 'ui designer', 'ux/ui', 'graphic designer',
        'visual designer', 'motion designer', 'brand designer', 'art director',
        
        // Non-technical Management
        'project manager', 'digital project manager', 'program manager', 'scrum master',
        'agile coach', 'delivery manager', 'product manager'
    ];

    /**
     * Positive technical role keywords.
     * At least one must be present in the job title or it must be clearly technical.
     *
     * @var string[]
     */
    protected static array $technicalTitles = [
        'developer', 'engineer', 'programmer', 'architect', 'coder', 'full stack', 'fullstack',
        'backend', 'back-end', 'frontend', 'front-end', 'software', 'devops', 'sre',
        'cloud', 'data scientist', 'data science', 'machine learning', 'ml engineer',
        'ai engineer', 'ai researcher', 'artificial intelligence', 'data engineer',
        'database', 'dba', 'sysadmin', 'systems engineer', 'qa engineer', 'test engineer',
        'tech lead', 'technical lead', 'engineering manager', 'director of engineering',
        'head of engineering', 'cto', 'solution architect', 'solutions architect',
        'web developer', 'mobile developer', 'ios', 'android', 'flutter', 'react', 'node',
        'python', 'php', 'laravel', 'golang', 'ruby', 'java', 'c++', 'c#', '.net'
    ];

    /**
     * Visa / Citizenship / Security Clearance disqualifiers in job description.
     *
     * @var string[]
     */
    protected static array $visaDisqualifiers = [
        'us citizen only',
        'us citizenship required',
        'u.s. citizen only',
        'u.s. citizenship required',
        'must be a us citizen',
        'must be a u.s. citizen',
        'united states citizenship required',
        'security clearance required',
        'active security clearance',
        'secret clearance',
        'top secret clearance',
        'ts/sci',
        'dod clearance',
        'must reside in the united states',
        'must be located in the us',
        'must reside in the us',
        'must be based in the uk',
        'must reside in the uk',
        'must reside in canada',
        'must be based in canada',
        'must reside in australia',
        'authorized to work in the us without sponsorship',
        'authorized to work in the united states without sponsorship',
        'no visa sponsorship',
        'unable to sponsor',
        'not able to sponsor',
        'cannot sponsor',
        'us work authorization required',
        'work authorization in the us',
        'must be legally authorized to work in the us',
        'must be legally authorized to work in the united states',
        'us candidates only',
        'only us candidates',
        'must be physically located in the us',
        'must be located in north america',
        'only candidates based in north america',
    ];

    /**
     * Eligible target locations using regex word boundaries to avoid false positives (e.g., 'oman' in 'romania').
     *
     * @var string[]
     */
    protected static array $eligibleLocationPatterns = [
        'remote', 'worldwide', 'anywhere', 'global', 'telecommute', 'virtual', 'work from home',
        'saudi', 'saudi arabia', 'riyadh', 'jeddah', 'khobar', 'dammam', '\bksa\b',
        'emirates', 'united arab emirates', 'dubai', 'abu dhabi', 'sharjah', '\buae\b',
        'kuwait', 'qatar', 'doha', 'bahrain', 'manama', '\boman\b', 'muscat',
        '\bgcc\b', 'middle east', '\bmena\b', '\bemea\b'
    ];

    /**
     * Check if a job title represents a legitimate technical role.
     */
    public static function isTechnicalRole(string $title): bool
    {
        $cleanTitle = strtolower(trim($title));

        // 1. If explicit non-tech title matches -> immediately reject
        foreach (self::$nonTechnicalTitles as $negative) {
            if (str_contains($cleanTitle, $negative)) {
                return false;
            }
        }

        // 2. Must contain at least one positive technical indicator
        foreach (self::$technicalTitles as $positive) {
            if (str_contains($cleanTitle, $positive)) {
                return true;
            }
        }

        // If title doesn't match either, default to false (strict tech filter)
        return false;
    }

    /**
     * Check if a job is location-eligible and free of visa/citizenship restrictions for GCC candidates.
     *
     * @return array{eligible: bool, reason: ?string}
     */
    public static function isLocationEligible(string $location, string $description): array
    {
        $descLower = strtolower($description);
        $locLower  = strtolower(trim($location));

        // 1. Check for hard citizenship / clearance barriers in description
        foreach (self::$visaDisqualifiers as $disqualifier) {
            if (str_contains($descLower, $disqualifier)) {
                return [
                    'eligible' => false,
                    'reason'   => "Disqualified: requires {$disqualifier}",
                ];
            }
        }

        // 2. If location is specified, it must either be Remote / Worldwide or GCC / MENA
        if ($locLower !== '' && $locLower !== 'not specified') {
            $isTargetLocation = false;
            foreach (self::$eligibleLocationPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $locLower)) {
                    $isTargetLocation = true;
                    break;
                }
            }

            if (!$isTargetLocation) {
                // Location is strictly pinned to an ineligible on-site region (e.g. "Chicago, IL", "London, UK", "Bucharest, Romania")
                return [
                    'eligible' => false,
                    'reason'   => "Disqualified: location '{$location}' is not GCC or Remote",
                ];
            }
        }

        return [
            'eligible' => true,
            'reason'   => null,
        ];
    }

    /**
     * Combined eligibility check for the Autonomous Job Hunter.
     *
     * @return array{eligible: bool, reason: ?string}
     */
    public static function isEligible(string $title, string $description = '', string $location = ''): array
    {
        if (!self::isTechnicalRole($title)) {
            return [
                'eligible' => false,
                'reason'   => "Disqualified: non-technical role title '{$title}'",
            ];
        }

        $locationCheck = self::isLocationEligible($location, $description);
        if (!$locationCheck['eligible']) {
            return $locationCheck;
        }

        return [
            'eligible' => true,
            'reason'   => null,
        ];
    }
}
