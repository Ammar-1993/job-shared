<?php

namespace App\Support;

/**
 * Builds the plain, structured text that is sent to the embedding model.
 *
 * Resumes and job vacancies MUST be formatted by this one class so both
 * vectors live in the same "language" — a raw json_encode() adds braces,
 * keys and escaped unicode noise that dilutes the semantic signal.
 *
 * Bump VERSION whenever the format changes, then re-run `embeddings:rebuild`.
 */
class EmbeddingText
{
    public const VERSION = 3;

    /** Stay well under text-embedding-3-small's 8191-token input limit. */
    private const MAX_CHARS = 7000;
    private const MAX_DESCRIPTION_CHARS = 2200;

    /** Section header detection regex */
    private const HEADER_REGEX = '/^(?:#{1,4}\s+|(?:\*\*|__)?)\s*([A-Za-z0-9\s,\'’&\/\-\(\)\?]{3,60}?)\s*(?:\*\*|__)?(?:\s*:)?\s*$/u';

    /** Core requirements, qualifications, skills, and tech stack headers */
    private const REQUIREMENTS_PATTERN = '/(?:requirement|qualification|what you(?:’|\')?ll bring|what you bring|what we(?:’|\')?re looking for|what we look for|what you(?:’|\')?ll need|what you need|who you are|about you|you have|you(?:’|\')?ve got|you\s+(?:should\s+|must\s+)?(?:also\s+)?have|skills|tech stack|technologies|must have|minimum|preferred|nice to have|bonus|profile|competenc|what we expect|experience)/i';

    /** Responsibilities, duties, and day-to-day role work headers */
    private const RESPONSIBILITIES_PATTERN = '/(?:responsibilit|what you(?:’|\')?ll do|what you will do|what you will be doing|what you(?:’|\')?ll be doing|you will|duties|your role|the role|about the role|role overview|scope of work|day to day|day-to-day|what you will accomplish|key tasks)/i';

    /** General role overview or summary headers */
    private const OVERVIEW_PATTERN = '/^(?:an\s+)?(?:overview|summary|position overview|about the job|about the position|job description)$/i';

    /** Pure boilerplate, marketing, perks, or legal disclaimers headers */
    private const BOILERPLATE_PATTERN = '/(?:compensation|benefit|perk|what we offer|why join|why work with us|why you should join|our process|interview process|hiring process|how to apply|apply now|about [a-z0-9_\-\.\s]+|about us|who we are|company overview|our mission|equal opportunity|eeo|diversity|affirmative action|privacy policy|country hiring|not your tech stack|p\.s\.|disclaimer|scam warning|salary)/i';

    /**
     * @param  array{title?:?string,description?:?string,location?:?string,type?:?string}  $job
     */
    public static function forJob(array $job): string
    {
        $lines = [];

        $title = self::clean($job['title'] ?? '');
        if ($title !== '') {
            $lines[] = "Job title: {$title}";
        }

        $type = self::clean($job['type'] ?? '');
        if ($type !== '') {
            $lines[] = "Employment type: {$type}";
        }

        $location = self::clean($job['location'] ?? '');
        if ($location !== '') {
            $lines[] = "Location: {$location}";
        }

        $description = self::extractCoreRequirements($job['description'] ?? '');
        if ($description !== '') {
            $lines[] = "Role requirements and responsibilities:\n{$description}";
        }

        return self::finish($lines);
    }

    /**
     * Extracts core requirements, responsibilities, and qualifications from the job description,
     * discarding company marketing, perks, benefits, EEO disclaimers, and application links.
     */
    public static function extractCoreRequirements(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Pre-normalize: if headings are jammed together without newlines (e.g. "ambiguity.A day in the life")
        $text = preg_replace('/([a-z0-9\.\)])([A-Z][A-Za-z0-9\s,\'’&\/\-\(\)\?\*]{3,50}?(?:\(Responsibilities\)|\(Requirements\)|\(Nice to Have\)|Requirements:|Responsibilities:|What you’ll do|What we’re looking for))/u', "$1\n\n$2", $text);

        $lines = explode("\n", $text);
        $sections = [];
        $currentHeader = 'intro';
        $currentLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if (!empty($currentLines)) {
                    $currentLines[] = '';
                }
                continue;
            }

            $hasMarkdown = preg_match('/^#{1,4}\s+/u', $trimmed);
            $hasBold = preg_match('/^(?:\*\*|__)[^*_]+(?:\*\*|__):?$/u', $trimmed);
            $hasColon = str_ends_with($trimmed, ':') && strlen($trimmed) <= 50;

            if (strlen($trimmed) <= 60 && !preg_match('/^[•\-\*\d\.]/', $trimmed) && preg_match(self::HEADER_REGEX, $trimmed, $m)) {
                $candidate = trim($m[1]);
                $isExplicitKeyword = preg_match(self::REQUIREMENTS_PATTERN, $candidate)
                    || preg_match(self::RESPONSIBILITIES_PATTERN, $candidate)
                    || preg_match(self::OVERVIEW_PATTERN, $candidate)
                    || preg_match(self::BOILERPLATE_PATTERN, $candidate);

                $isHeader = $isExplicitKeyword || (($hasMarkdown || $hasBold || $hasColon) && strlen($candidate) <= 50);

                if ($isHeader) {
                    if (!empty($currentLines)) {
                        $sections[] = [
                            'header' => $currentHeader,
                            'content' => trim(implode("\n", $currentLines)),
                        ];
                    }
                    $currentHeader = $candidate;
                    $currentLines = [];
                    continue;
                }
            }

            $currentLines[] = $line;
        }

        if (!empty($currentLines)) {
            $sections[] = [
                'header' => $currentHeader,
                'content' => trim(implode("\n", $currentLines)),
            ];
        }

        $requirements = [];
        $responsibilities = [];
        $overview = [];

        foreach ($sections as $sec) {
            $h = $sec['header'];
            $c = $sec['content'];

            if ($c === '') {
                continue;
            }

            $isReq = preg_match(self::REQUIREMENTS_PATTERN, $h);
            $isResp = preg_match(self::RESPONSIBILITIES_PATTERN, $h);
            $isOverview = preg_match(self::OVERVIEW_PATTERN, $h);

            // Requirements and responsibilities take absolute precedence over boilerplate
            if ($isReq) {
                $requirements[] = "{$h}:\n{$c}";
                continue;
            }
            if ($isResp) {
                $responsibilities[] = "{$h}:\n{$c}";
                continue;
            }
            if ($isOverview) {
                $overview[] = "{$h}:\n{$c}";
                continue;
            }

            // Skip boilerplate sections
            if (preg_match(self::BOILERPLATE_PATTERN, $h)) {
                continue;
            }
        }

        // Prioritized stitching: Requirements first, then Responsibilities, then Overview
        $combined = [];
        if (!empty($requirements)) {
            $combined = array_merge($combined, $requirements);
        }
        if (!empty($responsibilities)) {
            $combined = array_merge($combined, $responsibilities);
        }
        if (!empty($overview)) {
            $combined = array_merge($combined, $overview);
        }

        // Fallback: If no structured sections were captured (e.g. unformatted / brief text)
        if (empty($combined)) {
            $filteredLines = [];
            foreach ($lines as $line) {
                $t = trim($line);
                // Skip header metadata lines
                if (preg_match('/^(?:headquarters|url|company|job id)\s*:/i', $t)) {
                    continue;
                }
                // Stop when footer boilerplate starts
                if (preg_match('/^(?:to apply:|apply at:|how to apply:|equal opportunity employer|eeo is the law|please note that we welcome interest)/i', $t)) {
                    break;
                }
                $filteredLines[] = $line;
            }
            $fallbackText = trim(implode("\n", $filteredLines));
            $fallbackText = preg_replace("/[ \t]+/u", ' ', $fallbackText);
            $fallbackText = preg_replace("/\n{3,}/u", "\n\n", $fallbackText);

            return mb_substr($fallbackText, 0, self::MAX_DESCRIPTION_CHARS);
        }

        $result = implode("\n\n", $combined);
        $result = preg_replace("/[ \t]+/u", ' ', $result);
        $result = preg_replace("/\n{3,}/u", "\n\n", $result);

        return mb_substr(trim($result), 0, self::MAX_DESCRIPTION_CHARS);
    }

    /**
     * @param  array{summary?:mixed,skills?:mixed,experience?:mixed,education?:mixed}  $resume
     */
    public static function forResume(array $resume): string
    {
        $lines = [];

        $experience = self::asList($resume['experience'] ?? []);
        $titles = [];
        foreach ($experience as $item) {
            if (is_array($item) && ! empty($item['job_title'])) {
                $titles[] = self::clean($item['job_title']);
            }
        }
        if ($titles) {
            $lines[] = 'Professional titles: '.implode('; ', array_unique($titles));
        }

        $summary = self::clean(self::scalar($resume['summary'] ?? ''), preserveNewlines: true);
        if ($summary !== '') {
            $lines[] = "Professional summary: {$summary}";
        }

        $skills = array_filter(array_map(
            fn ($s) => self::clean(self::scalar($s)),
            self::asList($resume['skills'] ?? [])
        ));
        if ($skills) {
            $lines[] = 'Core skills: '.implode('; ', $skills);
        }

        if ($experience) {
            $rows = [];
            foreach ($experience as $item) {
                $rows[] = '- '.self::formatExperience($item);
            }
            $lines[] = "Work experience:\n".implode("\n", array_filter($rows, fn ($r) => trim($r) !== '-'));
        }

        $education = self::asList($resume['education'] ?? []);
        if ($education) {
            $rows = [];
            foreach ($education as $item) {
                $rows[] = '- '.self::formatEducation($item);
            }
            $lines[] = "Education:\n".implode("\n", array_filter($rows, fn ($r) => trim($r) !== '-'));
        }

        return self::finish($lines);
    }

    private static function formatExperience(mixed $item): string
    {
        if (! is_array($item)) {
            return self::clean(self::scalar($item));
        }

        $head = trim(self::clean($item['job_title'] ?? '').
            (! empty($item['company']) ? ' at '.self::clean($item['company']) : ''));
        if (! empty($item['duration'])) {
            $head .= ' ('.self::clean($item['duration']).')';
        }
        $desc = self::clean($item['description'] ?? '');

        return trim($head.($desc !== '' ? ': '.$desc : ''));
    }

    private static function formatEducation(mixed $item): string
    {
        if (! is_array($item)) {
            return self::clean(self::scalar($item));
        }

        $parts = array_filter([
            self::clean($item['degree'] ?? ''),
            self::clean($item['institution'] ?? ''),
            self::clean($item['graduation_year'] ?? ''),
        ]);

        return implode(', ', $parts);
    }

    private static function asList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : ($value === '' ? [] : [$value]);
        }

        return is_array($value) ? array_values($value) : [];
    }

    private static function scalar(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => self::scalar($v), $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function clean(mixed $text, bool $preserveNewlines = false): string
    {
        $text = html_entity_decode(strip_tags(self::scalar($text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($preserveNewlines) {
            $text = preg_replace("/[ \t]+/u", ' ', $text);
            $text = preg_replace("/\n\s*\n+/u", "\n", $text);
        } else {
            $text = preg_replace('/\s+/u', ' ', $text);
        }

        return trim($text ?? '');
    }

    private static function finish(array $lines): string
    {
        return mb_substr(implode("\n", $lines), 0, self::MAX_CHARS);
    }
}
