<?php

namespace App\Support;

/**
 * Calculates a human-interpretable Hybrid Match Score by combining:
 * 1) Semantic Vector Similarity (calibrated from text-embedding-3-small)
 * 2) Explicit Skills Matching (boundary & alias-aware keyword matching)
 */
class SkillMatcher
{
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
        // <= 0.18: completely unrelated domain (AML, Sales for Developer) -> 0 - 15%
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
     * Common / ancillary skills and buzzwords that should not alone constitute a core technical stack match.
     *
     * @var string[]
     */
    protected static array $ancillarySkills = [
        'testing', 'training', 'docs', 'documentation', 'git', 'github', 'gitlab', 'ci/cd',
        'rest', 'restful', 'restful apis', 'mvc', 'rbac', 'storage', 'queues', 'schedule',
        'agile', 'scrum', 'jira', 'management', 'leadership', 'communication', 'problem solving',
        'troubleshooting', 'teamwork', 'collaboration', 'unit testing', 'code review'
    ];

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

        // Core skills contribute 3 points each; ancillary skills contribute 1 point (capped at 3 points)
        $corePoints      = $coreCount * 3;
        $ancillaryPoints = min(3, $ancillaryCount);
        $totalPoints     = $corePoints + $ancillaryPoints;

        // Target points: 10 points (e.g. 3 core skills = 9 pts + 1 ancillary = 10 pts)
        $targetPoints = 10;
        $baseRatio    = min(1.0, $totalPoints / $targetPoints);

        // Base stack coverage contributes up to 80 points
        $skillsScore = (int) round($baseRatio * 80);

        // Safety cap: if NO core technical skills matched (only generic words like Testing/Git), cap at 35%
        if ($coreCount === 0 && $matchedCount > 0) {
            $skillsScore = min(35, $skillsScore);
        }

        // Title mention bonus (up to 10 points)
        if ($titleCount > 0) {
            $skillsScore += 10;
        }

        // Role title synergy bonus (e.g. Software Engineer applying to Software Engineer role)
        if (preg_match('/(?:frontend|fullstack|full-stack|full stack|backend|web developer|software engineer)/i', $jobTitle)) {
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

        // JavaScript framework variations: React.js -> react(?:\.js|js)?
        if (str_ends_with($lower, '.js')) {
            $base = preg_quote(substr($lower, 0, -3), '/');
            return '/(?:\b|_)' . $base . '(?:\.js|js)?(?:\b|_)/i';
        }

        // HTML / HTML5
        if (preg_match('/^(?:html5|html)$/i', $skill)) {
            return '/\bhtml(?:5)?\b/i';
        }

        // CSS / CSS3
        if (preg_match('/^(?:css3|css)$/i', $skill)) {
            return '/\bcss(?:3)?\b/i';
        }

        // Tailwind / Tailwind CSS
        if (preg_match('/tailwind(?:\s*css)?/i', $skill)) {
            return '/\btailwind(?:\s*css)?\b/i';
        }

        // Responsive design
        if (preg_match('/responsive(?:\s*design)?/i', $skill)) {
            return '/\bresponsive(?:\s*design|\s*web|\s*ui)?\b/i';
        }

        // PostgreSQL / Postgres
        if (preg_match('/^(?:postgresql|postgres)$/i', $skill)) {
            return '/\b(?:postgresql|postgres)\b/i';
        }

        // RESTful APIs / REST APIs
        if (preg_match('/rest(?:ful)?(?:\s*api(?:s)?)?/i', $skill)) {
            return '/\brest(?:ful)?(?:\s*api(?:s)?)?\b/i';
        }

        // AWS / Amazon Web Services
        if (preg_match('/^(?:aws|amazon web services)$/i', $skill)) {
            return '/\b(?:aws|amazon web services)\b/i';
        }

        // Default: word boundary if alphanumeric, literal otherwise (e.g. C++, C#, .NET)
        if (preg_match('/^[a-z0-9\s]+$/i', $skill)) {
            return '/\b' . preg_quote($skill, '/') . '\b/i';
        }

        return '/' . preg_quote($skill, '/') . '/i';
    }

    /**
     * Detect specialized technical domain track mismatch between candidate skills and job title.
     *
     * @param string[] $candidateSkills
     * @return array{mismatch: bool, domain: string, penalty: int, reason: ?string}
     */
    public static function detectTrackMismatch(array $candidateSkills, string $jobTitle, string $jobDescription): array
    {
        // Track 1: Data Science / Machine Learning / AI Research / Data Analytics
        if (preg_match('/(?:data scientist|machine learning|ml engineer|ai researcher|deep learning|data analyst|nlp engineer|computer vision|bi developer|data analytics|analytics engineer|big data)/i', $jobTitle)) {
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
                    'penalty'  => 25,
                    'reason'   => 'Role requires dedicated Data Science / ML track qualifications',
                ];
            }
        }

        // Track 2: Native Mobile Development (iOS / Android / Flutter)
        if (preg_match('/(?:ios developer|android developer|flutter developer|react native developer|mobile engineer|swift developer|kotlin developer)/i', $jobTitle)) {
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
                    'penalty'  => 25,
                    'reason'   => 'Role requires dedicated Mobile Development expertise',
                ];
            }
        }

        // Track 3: Cybersecurity / Information Security
        if (preg_match('/(?:security engineer|cybersecurity|penetration tester|soc analyst|infosec|cloud security)/i', $jobTitle)) {
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
                    'penalty'  => 25,
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
     *   track_mismatch: bool,
     *   track_domain: string,
     *   track_reason: ?string,
     *   summary: string
     * }
     */
    public static function computeHybridScore(
        ?array $resumeEmbedding,
        ?array $jobEmbedding,
        array $candidateSkills,
        string $jobTitle,
        string $jobDescription
    ): array {
        $hasVectors = !empty($resumeEmbedding) && !empty($jobEmbedding);
        $rawCosine  = $hasVectors ? self::cosineSimilarity($resumeEmbedding, $jobEmbedding) : 0.0;
        $vectorScore = $hasVectors ? self::calibrateVectorScore($rawCosine) : 0;

        // Detect Domain / Track Mismatch
        $trackInfo = self::detectTrackMismatch($candidateSkills, $jobTitle, $jobDescription);
        $adjustedVector = $trackInfo['mismatch'] ? max(10, $vectorScore - $trackInfo['penalty']) : $vectorScore;

        $skillMatch = self::matchSkills($candidateSkills, $jobTitle, $jobDescription);
        $skillsScore = $skillMatch['skills_score'];
        if ($trackInfo['mismatch']) {
            $skillsScore = min(50, $skillsScore);
        }

        // Determine Composite Score
        if ($hasVectors) {
            // Unrelated domain safety filter: if no skills matched and vector is low, heavily discount
            if ($skillMatch['match_count'] === 0 && $adjustedVector < 45) {
                $composite = (int) round($adjustedVector * 0.45);
            } else {
                // 55% Semantic Vector + 45% Explicit Skills
                $composite = (int) round((0.55 * $adjustedVector) + (0.45 * $skillsScore));
            }
        } else {
            // Fallback purely to skills if embeddings are absent
            $composite = $skillsScore;
        }

        if ($trackInfo['mismatch']) {
            $composite = min(55, $composite);
        }

        $composite = max(0, min(100, $composite));

        // Generate concise human summary
        $summary = $skillMatch['match_count'] > 0
            ? $skillMatch['match_count'] . ' skills matched (' . implode(', ', array_slice($skillMatch['matched'], 0, 3)) . (count($skillMatch['matched']) > 3 ? '...' : '') . ')'
            : 'No direct skill keywords found';

        if ($trackInfo['mismatch']) {
            $summary .= ' • ' . $trackInfo['reason'];
        }

        return [
            'composite_score' => $composite,
            'vector_score'    => $adjustedVector,
            'raw_cosine'      => round($rawCosine, 4),
            'skills_score'    => $skillsScore,
            'matched_skills'  => $skillMatch['matched'],
            'core_skills'     => $skillMatch['core_matched'],
            'ancillary_skills'=> $skillMatch['ancillary_matched'],
            'in_title_skills' => $skillMatch['in_title'],
            'missing_skills'  => $skillMatch['missing'],
            'match_count'     => $skillMatch['match_count'],
            'track_mismatch'  => $trackInfo['mismatch'],
            'track_domain'    => $trackInfo['domain'],
            'track_reason'    => $trackInfo['reason'],
            'summary'         => $summary,
        ];
    }
}
