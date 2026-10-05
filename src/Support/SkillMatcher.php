<?php

namespace App\Support;

/**
 * Calculates a human-interpretable Hybrid Match Score by combining:
 * 1) Semantic Vector Similarity (calibrated from text-embedding-3-small)
 * 2) Explicit Core vs. Ancillary Skills Matching (boundary & alias-aware)
 * 3) Strict Tech Stack Guard (identifies language/framework mismatches like Ruby, Java, .NET)
 * 4) Seniority & Experience Level Alignment (differentiates Internship/Junior vs Senior/Lead)
 * 5) Domain Track Verification (Data Science, Mobile, Cybersecurity)
 */
class SkillMatcher
{
    /**
     * Common / ancillary skills and buzzwords that should not alone constitute a core technical stack match.
     *
     * @var string[]
     */
    protected static array $ancillarySkills = [
        'testing', 'training', 'docs', 'documentation', 'git', 'github', 'gitlab', 'ci/cd',
        'rest', 'restful', 'restful apis', 'mvc', 'rbac', 'storage', 'queues', 'schedule',
        'agile', 'scrum', 'jira', 'management', 'leadership', 'communication', 'problem solving',
        'troubleshooting', 'teamwork', 'collaboration', 'unit testing', 'code review',
        'responsive design', 'web development', 'clean code', 'design patterns', 'linux'
    ];

    /**
     * Compute cosine similarity between two vector embeddings (0.0 - 1.0).
     *
     * @param float[]|null $vec1
     * @param float[]|null $vec2
     */
    public static function cosineSimilarity(?array $vec1, ?array $vec2): float
    {
        if (empty($vec1) || empty($vec2) || count($vec1) !== count($vec2)) {
            return 0.0;
        }

        $dot = 0.0;
        $m1  = 0.0;
        $m2  = 0.0;

        foreach ($vec1 as $i => $v1) {
            $v2 = $vec2[$i];
            $dot += $v1 * $v2;
            $m1  += $v1 * $v1;
            $m2  += $v2 * $v2;
        }

        if ($m1 == 0 || $m2 == 0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($m1) * sqrt($m2))));
    }

    /**
     * Calibrate raw cosine similarity (typically 0.18 - 0.58 in text-embedding-3-small)
     * to a human-interpretable 0 - 100 semantic scale.
     */
    public static function calibrateVectorScore(float $cosine): int
    {
        // <= 0.18: completely unrelated domain -> 0 - 15%
        if ($cosine <= 0.18) {
            return (int) round(($cosine / 0.18) * 15);
        }

        // 0.18 - 0.35: distant / general business similarity -> 15 - 50%
        if ($cosine <= 0.35) {
            $t = ($cosine - 0.18) / (0.35 - 0.18);
            return (int) round(15 + ($t * 35));
        }

        // 0.35 - 0.50: relevant domain / good match -> 50 - 85%
        if ($cosine <= 0.50) {
            $t = ($cosine - 0.35) / (0.50 - 0.35);
            return (int) round(50 + ($t * 35));
        }

        // 0.50 - 0.60+: exceptional / near-perfect alignment -> 85 - 100%
        $t = min(1.0, ($cosine - 0.50) / (0.60 - 0.50));
        return (int) round(85 + ($t * 15));
    }

    /**
     * Match a candidate's explicit skills against the job title and requirements/description.
     *
     * @param string[] $candidateSkills
     * @return array{
     *   matched: string[],
     *   core_matched: string[],
     *   ancillary_matched: string[],
     *   in_title: string[],
     *   missing: string[],
     *   match_count: int,
     *   core_count: int,
     *   skills_score: int
     * }
     */
    public static function matchSkills(array $candidateSkills, string $jobTitle, string $jobDescription): array
    {
        $matched          = [];
        $coreMatched      = [];
        $ancillaryMatched = [];
        $inTitle          = [];
        $missing          = [];

        $titleLower    = strtolower($jobTitle);
        $fullTextLower = strtolower($jobTitle . ' ' . $jobDescription);

        foreach ($candidateSkills as $skill) {
            $clean = trim((string) $skill);
            if ($clean === '') {
                continue;
            }

            $regex = self::buildSkillRegex($clean);

            $matchedInDesc  = (bool) preg_match($regex, $fullTextLower);
            $matchedInTitle = (bool) preg_match($regex, $titleLower);

            if ($matchedInDesc || $matchedInTitle) {
                $matched[] = $clean;
                if (in_array(strtolower($clean), self::$ancillarySkills, true)) {
                    $ancillaryMatched[] = $clean;
                } else {
                    $coreMatched[] = $clean;
                }

                if ($matchedInTitle) {
                    $inTitle[] = $clean;
                }
            } else {
                $missing[] = $clean;
            }
        }

        $matchedCount   = count($matched);
        $coreCount      = count($coreMatched);
        $ancillaryCount = count($ancillaryMatched);
        $titleCount     = count($inTitle);

        // Core skills contribute 5 points each; ancillary contribute 1 point (capped at 3 points)
        $corePoints      = $coreCount * 5;
        $ancillaryPoints = min(3, $ancillaryCount);
        $totalPoints     = $corePoints + $ancillaryPoints;

        // Target points: 15 points (e.g. 3 core skills = 15 pts)
        $targetPoints = 15;
        $baseRatio    = min(1.0, $totalPoints / $targetPoints);

        // Base stack coverage contributes up to 80 points
        $skillsScore = (int) round($baseRatio * 80);

        // Safety cap: if NO core technical skills matched (only generic words like Testing/Git/Agile), hard cap at 20%
        if ($coreCount === 0) {
            $skillsScore = min(20, $skillsScore);
        }

        // Title mention bonus for core skills (up to 10 points)
        if ($titleCount > 0 && $coreCount > 0) {
            $skillsScore += 10;
        }

        // Role title synergy bonus (e.g. Software Engineer / Frontend / Fullstack)
        if (preg_match('/(?<=^|[\s,.\-\/_\(\)\[\]:&|])(?:frontend|fullstack|full-stack|full stack|backend|web developer|software engineer)(?=$|[\s,.\-\/_\(\)\[\]:&|])/i', $jobTitle)) {
            $skillsScore += 10;
        }

        $skillsScore = max(0, min(100, $skillsScore));

        return [
            'matched'           => $matched,
            'core_matched'      => $coreMatched,
            'ancillary_matched' => $ancillaryMatched,
            'in_title'          => $inTitle,
            'missing'           => $missing,
            'match_count'       => $matchedCount,
            'core_count'        => $coreCount,
            'skills_score'      => $skillsScore,
        ];
    }

    /**
     * Builds a boundary-safe and alias-aware regular expression for a technical skill.
     */
    private static function buildSkillRegex(string $skill): string
    {
        $lower = strtolower($skill);
        $delim = '(?<=^|[\s,.\-\/_\(\)\[\]:&|])';
        $endDelim = '(?=$|[\s,.\-\/_\(\)\[\]:&|])';

        // JavaScript framework variations: React.js -> react(?:\.js|js)?
        if (str_ends_with($lower, '.js')) {
            $base = preg_quote(substr($lower, 0, -3), '/');
            return '/' . $delim . $base . '(?:\.js|js)?' . $endDelim . '/i';
        }

        // HTML / HTML5
        if (preg_match('/^(?:html5|html)$/i', $skill)) {
            return '/' . $delim . 'html(?:5)?' . $endDelim . '/i';
        }

        // CSS / CSS3
        if (preg_match('/^(?:css3|css)$/i', $skill)) {
            return '/' . $delim . 'css(?:3)?' . $endDelim . '/i';
        }

        // Tailwind / Tailwind CSS
        if (preg_match('/tailwind(?:\s*css)?/i', $skill)) {
            return '/' . $delim . 'tailwind(?:\s*css)?' . $endDelim . '/i';
        }

        // Responsive design
        if (preg_match('/responsive(?:\s*design)?/i', $skill)) {
            return '/' . $delim . 'responsive(?:\s*design|\s*web|\s*ui)?' . $endDelim . '/i';
        }

        // PostgreSQL / Postgres
        if (preg_match('/^(?:postgresql|postgres)$/i', $skill)) {
            return '/' . $delim . '(?:postgresql|postgres)' . $endDelim . '/i';
        }

        // RESTful APIs / REST APIs
        if (preg_match('/rest(?:ful)?(?:\s*api(?:s)?)?/i', $skill)) {
            return '/' . $delim . 'rest(?:ful)?(?:\s*api(?:s)?)?' . $endDelim . '/i';
        }

        // AWS / Amazon Web Services
        if (preg_match('/^(?:aws|amazon web services)$/i', $skill)) {
            return '/' . $delim . '(?:aws|amazon web services)' . $endDelim . '/i';
        }

        return '/' . $delim . preg_quote($skill, '/') . $endDelim . '/i';
    }

    /**
     * Detect Seniority Level of a Job posting from Title and Requirements.
     *
     * @return array{level: string, label: string}
     */
    public static function detectJobSeniority(string $jobTitle, string $jobDescription = ''): array
    {
        $titleLower = strtolower($jobTitle);
        $delim = '(?<=^|[\s,.\-\/_\(\)\[\]:&|])';
        $endDelim = '(?=$|[\s,.\-\/_\(\)\[\]:&|])';

        // 1. Internship / Co-op / Trainee
        if (preg_match('/' . $delim . '(?:intern|internship|trainee|apprentice|co-op|working student)' . $endDelim . '/i', $titleLower)) {
            return ['level' => 'internship', 'label' => 'Internship / Trainee'];
        }

        // 2. Junior / Entry-level / Graduate / Associate
        if (preg_match('/' . $delim . '(?:junior|jr|jr\.|entry level|entry-level|graduate developer|graduate engineer|associate developer|associate engineer|associate data scientist)' . $endDelim . '/i', $titleLower)) {
            return ['level' => 'junior', 'label' => 'Junior / Entry-level'];
        }

        // 3. Lead / Tech Lead / Management
        if (preg_match('/' . $delim . '(?:tech lead|team lead|technical lead|lead developer|lead engineer|engineering manager|head of engineering)' . $endDelim . '/i', $titleLower)) {
            return ['level' => 'lead', 'label' => 'Lead / Engineering Management'];
        }

        // 4. Senior / Staff / Principal
        if (preg_match('/' . $delim . '(?:senior|sr|sr\.|staff|principal)' . $endDelim . '/i', $titleLower)) {
            return ['level' => 'senior', 'label' => 'Senior / Staff'];
        }

        // 5. Executive (Director / VP / CTO)
        if (preg_match('/' . $delim . '(?:director of engineering|vp of engineering|vice president|chief technology officer|cto)' . $endDelim . '/i', $titleLower)) {
            return ['level' => 'executive', 'label' => 'Executive'];
        }

        // Default: Standard Mid-level Engineer
        return ['level' => 'mid', 'label' => 'Mid-level'];
    }

    /**
     * Detect Candidate Seniority Level from their skills, experience text, and summary.
     *
     * @param string[] $candidateSkills
     * @param mixed $experience
     * @return array{level: string, label: string}
     */
    public static function detectCandidateSeniority(array $candidateSkills, $experience = null, string $summary = ''): array
    {
        $expText = '';
        if (is_array($experience)) {
            $expText = json_encode($experience);
        } elseif (is_string($experience)) {
            $expText = $experience;
        }

        $combined = strtolower($expText . ' ' . $summary);
        $delim = '(?<=^|[\s,.\-\/_\(\)\[\]:&|])';
        $endDelim = '(?=$|[\s,.\-\/_\(\)\[\]:&|])';

        // Check if candidate has Senior / Lead roles in their experience history
        if (preg_match('/' . $delim . '(?:senior|sr\.|lead|principal|staff|tech lead)' . $endDelim . '/i', $combined)) {
            return ['level' => 'senior', 'label' => 'Senior / Lead'];
        }

        // If >= 6 skills and established background
        if (count($candidateSkills) >= 6) {
            return ['level' => 'senior', 'label' => 'Senior (Established Stack)'];
        }

        return ['level' => 'mid', 'label' => 'Mid-level'];
    }

    /**
     * Evaluate Seniority Level Alignment between Candidate and Job.
     *
     * @return array{mismatch: bool, penalty: int, bonus: int, reason: ?string, job_level: string, job_label: string}
     */
    public static function evaluateSeniorityAlignment(string $candidateLevel, string $jobTitle, string $jobDescription = ''): array
    {
        $jobSeniority = self::detectJobSeniority($jobTitle, $jobDescription);
        $level = $jobSeniority['level'];

        if ($candidateLevel === 'senior' || $candidateLevel === 'lead') {
            if ($level === 'internship') {
                return [
                    'mismatch'  => true,
                    'penalty'   => 45,
                    'bonus'     => 0,
                    'reason'    => 'Role is an Internship/Trainee (mismatches candidate senior profile)',
                    'job_level' => $level,
                    'job_label' => $jobSeniority['label'],
                ];
            }

            if ($level === 'junior') {
                return [
                    'mismatch'  => true,
                    'penalty'   => 25,
                    'bonus'     => 0,
                    'reason'    => 'Role is Junior/Entry-level (below your senior experience level)',
                    'job_level' => $level,
                    'job_label' => $jobSeniority['label'],
                ];
            }

            if ($level === 'executive') {
                return [
                    'mismatch'  => true,
                    'penalty'   => 20,
                    'bonus'     => 0,
                    'reason'    => 'Executive management role (not a hands-on IC or team lead position)',
                    'job_level' => $level,
                    'job_label' => $jobSeniority['label'],
                ];
            }

            // Senior on Senior -> Positive alignment bonus
            if ($level === 'senior') {
                return [
                    'mismatch'  => false,
                    'penalty'   => 0,
                    'bonus'     => 10,
                    'reason'    => null,
                    'job_level' => $level,
                    'job_label' => $jobSeniority['label'],
                ];
            }

            if ($level === 'lead') {
                return [
                    'mismatch'  => false,
                    'penalty'   => 0,
                    'bonus'     => 5,
                    'reason'    => null,
                    'job_level' => $level,
                    'job_label' => $jobSeniority['label'],
                ];
            }
        }

        return [
            'mismatch'  => false,
            'penalty'   => 0,
            'bonus'     => 0,
            'reason'    => null,
            'job_level' => $level,
            'job_label' => $jobSeniority['label'],
        ];
    }

    /**
     * Strict Tech Stack Alignment: detects if job explicitly targets an incompatible language or core framework.
     *
     * @param string[] $candidateSkills
     * @return array{mismatch: bool, penalty: int, bonus: int, required_stack: ?string, reason: ?string}
     */
    public static function detectStackAlignment(array $candidateSkills, string $jobTitle, string $jobDescription): array
    {
        $titleLower = strtolower($jobTitle);
        $candidateLower = array_map('strtolower', $candidateSkills);

        $delim = '(?<=^|[\s,.\-\/_\(\)\[\]:&|])';
        $endDelim = '(?=$|[\s,.\-\/_\(\)\[\]:&|])';

        $hasRuby = in_array('ruby', $candidateLower, true) || in_array('rails', $candidateLower, true) || in_array('ruby on rails', $candidateLower, true);
        $hasJava = false;
        foreach ($candidateLower as $s) {
            if ($s === 'java' || $s === 'spring' || $s === 'spring boot' || $s === 'springboot') {
                $hasJava = true;
                break;
            }
        }
        $hasDotNet = in_array('.net', $candidateLower, true) || in_array('c#', $candidateLower, true) || in_array('dotnet', $candidateLower, true) || in_array('asp.net', $candidateLower, true);
        $hasRust = in_array('rust', $candidateLower, true);
        $hasPhp = in_array('php', $candidateLower, true) || in_array('laravel', $candidateLower, true) || in_array('symfony', $candidateLower, true);
        $hasJsFrontend = false;
        foreach ($candidateLower as $s) {
            if (in_array($s, ['react', 'react.js', 'reactjs', 'vue', 'vue.js', 'typescript', 'javascript', 'next.js', 'nextjs', 'tailwind css', 'tailwind', 'redux'], true)) {
                $hasJsFrontend = true;
                break;
            }
        }
        $hasPython = in_array('python', $candidateLower, true) || in_array('django', $candidateLower, true) || in_array('fastapi', $candidateLower, true);
        $hasGo = in_array('go', $candidateLower, true) || in_array('golang', $candidateLower, true);

        // 1. Ruby Stack Check
        if (preg_match('/' . $delim . '(?:ruby|rails|ruby on rails)' . $endDelim . '/i', $titleLower)) {
            if (!$hasRuby) {
                return [
                    'mismatch'       => true,
                    'penalty'        => 35,
                    'bonus'          => 0,
                    'required_stack' => 'Ruby on Rails',
                    'reason'         => 'Role specifically targets Ruby on Rails (missing from your core stack)',
                ];
            }
        }

        // 2. Java Stack Check (excluding JavaScript)
        if (preg_match('/' . $delim . '(?:java|spring\s*boot|springboot)' . $endDelim . '/i', $titleLower) && !str_contains($titleLower, 'javascript')) {
            if (!$hasJava) {
                return [
                    'mismatch'       => true,
                    'penalty'        => 35,
                    'bonus'          => 0,
                    'required_stack' => 'Java / Spring',
                    'reason'         => 'Role specifically targets Java / Spring Boot (missing from your core stack)',
                ];
            }
        }

        // 3. .NET / C# Stack Check
        if (preg_match('/' . $delim . '(?:\.net|c#|dotnet|csharp|asp\.net)' . $endDelim . '/i', $titleLower)) {
            if (!$hasDotNet) {
                return [
                    'mismatch'       => true,
                    'penalty'        => 35,
                    'bonus'          => 0,
                    'required_stack' => '.NET / C#',
                    'reason'         => 'Role specifically targets .NET / C# (missing from your core stack)',
                ];
            }
        }

        // 4. Rust Stack Check
        if (preg_match('/' . $delim . 'rust' . $endDelim . '/i', $titleLower)) {
            if (!$hasRust) {
                return [
                    'mismatch'       => true,
                    'penalty'        => 35,
                    'bonus'          => 0,
                    'required_stack' => 'Rust',
                    'reason'         => 'Role specifically targets Rust (missing from your core stack)',
                ];
            }
        }

        // 5. Golang Check (if title specifically targets Go)
        if (preg_match('/' . $delim . '(?:golang|go developer|go engineer)' . $endDelim . '/i', $titleLower)) {
            if (!$hasGo) {
                return [
                    'mismatch'       => true,
                    'penalty'        => 25,
                    'bonus'          => 0,
                    'required_stack' => 'Golang',
                    'reason'         => 'Role specifically targets Golang',
                ];
            }
        }

        // Positive Affinity 1: Frontend / React / TypeScript Alignment
        if (preg_match('/' . $delim . '(?:frontend|front-end|react|vue|next\.js|typescript|web developer)' . $endDelim . '/i', $titleLower)) {
            if ($hasJsFrontend) {
                return [
                    'mismatch'       => false,
                    'penalty'        => 0,
                    'bonus'          => 15,
                    'required_stack' => 'Frontend / React',
                    'reason'         => null,
                ];
            }
        }

        // Positive Affinity 2: PHP / Laravel Alignment
        if (preg_match('/' . $delim . '(?:php|laravel|symfony)' . $endDelim . '/i', $titleLower)) {
            if ($hasPhp) {
                return [
                    'mismatch'       => false,
                    'penalty'        => 0,
                    'bonus'          => 15,
                    'required_stack' => 'PHP / Laravel',
                    'reason'         => null,
                ];
            } else {
                return [
                    'mismatch'       => true,
                    'penalty'        => 30,
                    'bonus'          => 0,
                    'required_stack' => 'PHP / Laravel',
                    'reason'         => 'Role specifically targets PHP / Laravel (missing from your stack)',
                ];
            }
        }

        // Positive Affinity 3: Python Alignment
        if (preg_match('/' . $delim . '(?:python|django|fastapi)' . $endDelim . '/i', $titleLower)) {
            if ($hasPython) {
                return [
                    'mismatch'       => false,
                    'penalty'        => 0,
                    'bonus'          => 15,
                    'required_stack' => 'Python',
                    'reason'         => null,
                ];
            }
        }

        return [
            'mismatch'       => false,
            'penalty'        => 0,
            'bonus'          => 0,
            'required_stack' => null,
            'reason'         => null,
        ];
    }

    /**
     * Detect specialized technical domain track mismatch between candidate skills and job title.
     *
     * @param string[] $candidateSkills
     * @return array{mismatch: bool, domain: string, penalty: int, reason: ?string}
     */
    public static function detectTrackMismatch(array $candidateSkills, string $jobTitle, string $jobDescription): array
    {
        $delim = '(?<=^|[\s,.\-\/_\(\)\[\]:&|])';
        $endDelim = '(?=$|[\s,.\-\/_\(\)\[\]:&|])';

        // Track 1: Data Science / Machine Learning / AI Research / Data Analytics
        if (preg_match('/' . $delim . '(?:data scientist|machine learning|ml engineer|ai researcher|deep learning|data analyst|nlp engineer|computer vision|bi developer|data analytics|analytics engineer|big data)' . $endDelim . '/i', $jobTitle)) {
            $hasDsSkill = false;
            foreach ($candidateSkills as $s) {
                if (preg_match('/^(?:data science|machine learning|deep learning|pytorch|tensorflow|scikit-learn|pandas|numpy|nlp|computer vision|data modeling|statistics|big data|hadoop|spark|tableau|power bi|keras)$/i', trim($s))) {
                    $hasDsSkill = true;
                    break;
                }
            }
            if (!$hasDsSkill) {
                return [
                    'mismatch' => true,
                    'domain'   => 'Data Science & AI',
                    'penalty'  => 30,
                    'reason'   => 'Role requires dedicated Data Science / ML track qualifications',
                ];
            }
        }

        // Track 2: Native Mobile Development (iOS / Android / Flutter)
        if (preg_match('/' . $delim . '(?:ios developer|android developer|flutter developer|react native developer|mobile engineer|swift developer|kotlin developer)' . $endDelim . '/i', $jobTitle)) {
            $hasMobileSkill = false;
            foreach ($candidateSkills as $s) {
                if (preg_match('/^(?:ios|android|swift|kotlin|flutter|react native|xcode|android studio|mobile development)$/i', trim($s))) {
                    $hasMobileSkill = true;
                    break;
                }
            }
            if (!$hasMobileSkill) {
                return [
                    'mismatch' => true,
                    'domain'   => 'Mobile Engineering',
                    'penalty'  => 30,
                    'reason'   => 'Role requires dedicated Mobile Development expertise',
                ];
            }
        }

        // Track 3: Cybersecurity / Information Security
        if (preg_match('/' . $delim . '(?:security engineer|cybersecurity|penetration tester|soc analyst|infosec|cloud security)' . $endDelim . '/i', $jobTitle)) {
            $hasSecSkill = false;
            foreach ($candidateSkills as $s) {
                if (preg_match('/^(?:cybersecurity|security|penetration testing|infosec|siem|soc|cryptography|owasp|network security|ethical hacking)$/i', trim($s))) {
                    $hasSecSkill = true;
                    break;
                }
            }
            if (!$hasSecSkill) {
                return [
                    'mismatch' => true,
                    'domain'   => 'Cybersecurity',
                    'penalty'  => 30,
                    'reason'   => 'Role requires dedicated Cybersecurity credentials',
                ];
            }
        }

        return [
            'mismatch' => false,
            'domain'   => 'Software Engineering',
            'penalty'  => 0,
            'reason'   => null,
        ];
    }

    /**
     * Computes the complete Hybrid Match package.
     *
     * @param float[]|null $resumeEmbedding
     * @param float[]|null $jobEmbedding
     * @param string[] $candidateSkills
     * @param mixed $candidateExperience
     * @return array{
     *   composite_score: int,
     *   vector_score: int,
     *   raw_cosine: float,
     *   skills_score: int,
     *   matched_skills: string[],
     *   core_skills: string[],
     *   ancillary_skills: string[],
     *   in_title_skills: string[],
     *   missing_skills: string[],
     *   match_count: int,
     *   core_count: int,
     *   track_mismatch: bool,
     *   track_domain: string,
     *   track_reason: ?string,
     *   seniority_level: string,
     *   seniority_label: string,
     *   seniority_mismatch: bool,
     *   seniority_reason: ?string,
     *   stack_mismatch: bool,
     *   stack_required: ?string,
     *   stack_reason: ?string,
     *   summary: string
     * }
     */
    public static function computeHybridScore(
        ?array $resumeEmbedding,
        ?array $jobEmbedding,
        array $candidateSkills,
        string $jobTitle,
        string $jobDescription,
        $candidateExperience = null
    ): array {
        $hasVectors = !empty($resumeEmbedding) && !empty($jobEmbedding);
        $rawCosine  = $hasVectors ? self::cosineSimilarity($resumeEmbedding, $jobEmbedding) : 0.0;
        $vectorScore = $hasVectors ? self::calibrateVectorScore($rawCosine) : 0;

        // 1. Candidate Seniority & Job Seniority Alignment
        $candidateSeniority = self::detectCandidateSeniority($candidateSkills, $candidateExperience);
        $seniorityEval = self::evaluateSeniorityAlignment($candidateSeniority['level'], $jobTitle, $jobDescription);

        // 2. Strict Stack Alignment
        $stackEval = self::detectStackAlignment($candidateSkills, $jobTitle, $jobDescription);

        // 3. Domain / Track Mismatch
        $trackInfo = self::detectTrackMismatch($candidateSkills, $jobTitle, $jobDescription);

        // Apply Vector Adjustments
        $adjustedVector = $vectorScore;
        if ($seniorityEval['mismatch']) {
            $adjustedVector = max(10, $adjustedVector - $seniorityEval['penalty']);
        }
        if ($stackEval['mismatch']) {
            $adjustedVector = max(10, $adjustedVector - $stackEval['penalty']);
        }
        if ($trackInfo['mismatch']) {
            $adjustedVector = max(10, $adjustedVector - $trackInfo['penalty']);
        }

        // Apply Explicit Skills Match
        $skillMatch  = self::matchSkills($candidateSkills, $jobTitle, $jobDescription);
        $skillsScore = $skillMatch['skills_score'];

        // Penalize or Boost Skills Score
        if ($stackEval['mismatch']) {
            $skillsScore = min(25, max(0, $skillsScore - $stackEval['penalty']));
        } elseif ($stackEval['bonus'] > 0) {
            $skillsScore = min(100, $skillsScore + $stackEval['bonus']);
        }

        if ($seniorityEval['mismatch']) {
            $skillsScore = min(30, max(0, $skillsScore - $seniorityEval['penalty']));
        } elseif ($seniorityEval['bonus'] > 0) {
            $skillsScore = min(100, $skillsScore + $seniorityEval['bonus']);
        }

        if ($trackInfo['mismatch']) {
            $skillsScore = min(35, max(0, $skillsScore - $trackInfo['penalty']));
        }

        // Determine Composite Score
        if ($hasVectors) {
            if ($skillMatch['core_count'] === 0 && $adjustedVector < 50) {
                // Without core skills and mediocre vector -> heavily discount
                $composite = (int) round($adjustedVector * 0.40);
            } else {
                // 50% Semantic Vector + 50% Explicit Skills
                $composite = (int) round((0.50 * $adjustedVector) + (0.50 * $skillsScore));
            }
        } else {
            $composite = $skillsScore;
        }

        // Hard Safety Caps on Mismatches
        if ($seniorityEval['mismatch'] && in_array($seniorityEval['job_level'], ['internship', 'junior'], true)) {
            $composite = min(30, $composite);
        }

        if ($stackEval['mismatch']) {
            $composite = min(35, $composite);
        }

        if ($trackInfo['mismatch']) {
            $composite = min(40, $composite);
        }

        $composite = max(0, min(100, $composite));

        // Generate concise human summary
        $summaryParts = [];
        if ($skillMatch['core_count'] > 0) {
            $summaryParts[] = $skillMatch['core_count'] . ' core skills matched (' . implode(', ', array_slice($skillMatch['core_matched'], 0, 3)) . ')';
        } elseif ($skillMatch['match_count'] > 0) {
            $summaryParts[] = $skillMatch['match_count'] . ' ancillary skills matched';
        } else {
            $summaryParts[] = 'No core skill keywords found';
        }

        if ($stackEval['mismatch']) {
            $summaryParts[] = $stackEval['reason'];
        }
        if ($seniorityEval['mismatch']) {
            $summaryParts[] = $seniorityEval['reason'];
        }
        if ($trackInfo['mismatch']) {
            $summaryParts[] = $trackInfo['reason'];
        }

        $summary = implode(' • ', $summaryParts);

        return [
            'composite_score'    => $composite,
            'vector_score'       => $adjustedVector,
            'raw_cosine'         => round($rawCosine, 4),
            'skills_score'       => $skillsScore,
            'matched_skills'     => $skillMatch['matched'],
            'core_skills'        => $skillMatch['core_matched'],
            'ancillary_skills'   => $skillMatch['ancillary_matched'],
            'in_title_skills'    => $skillMatch['in_title'],
            'missing_skills'     => $skillMatch['missing'],
            'match_count'        => $skillMatch['match_count'],
            'core_count'         => $skillMatch['core_count'],
            
            // Domain Track
            'track_mismatch'     => $trackInfo['mismatch'],
            'track_domain'       => $trackInfo['domain'],
            'track_reason'       => $trackInfo['reason'],
            
            // Seniority Alignment
            'seniority_level'    => $seniorityEval['job_level'],
            'seniority_label'    => $seniorityEval['job_label'],
            'seniority_mismatch' => $seniorityEval['mismatch'],
            'seniority_reason'   => $seniorityEval['reason'],
            
            // Strict Stack Alignment
            'stack_mismatch'     => $stackEval['mismatch'],
            'stack_required'     => $stackEval['required_stack'],
            'stack_reason'       => $stackEval['reason'],
            
            'summary'            => $summary,
        ];
    }
}
