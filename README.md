# Job Shared Library

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![OpenAI](https://img.shields.io/badge/OpenAI-Vector_Embeddings-412991?style=for-the-badge&logo=openai&logoColor=white)](https://openai.com)

## 📖 Introduction

The **Job Shared Library** (`job/shared`) is the core foundational package of the **Job Vacancies Platform & AI Autonomous Job Hunter Ecosystem**. It encapsulates the shared domain logic, database models, typed enums, AI vector matching algorithms, and external import interfaces used across both applications:
- **`job-app`** (Candidate Portal & Autonomous Job Hunter Dashboard)
- **`job-backoffice`** (Admin Control Center & Hunter Pipeline Tracker)

Centralizing these definitions ensures 100% data integrity, eliminates code duplication, and enables seamless micro-service collaboration across the platform.

---

## 📂 Architecture & Package Components

```
job-shared/
├── src/
│   ├── Enums/
│   │   ├── ApplicationStatus.php       # Standard candidate application lifecycle
│   │   ├── HunterApplicationStatus.php  # Personal job hunter stage lifecycle
│   │   ├── JobStatus.php               # Vacancy state (Active, Closed, Draft)
│   │   ├── JobType.php                 # Employment type (Full-time, Remote, Hybrid, etc.)
│   │   └── UserRole.php                # System roles (Admin, Company Owner, Candidate)
│   ├── Models/
│   │   ├── Company.php                 # Employer entity and profiles
│   │   ├── JobApplication.php          # Dual-mode applications (Client vs Personal Hunter)
│   │   ├── JobCategory.php             # Industry classification
│   │   ├── JobVacancy.php              # Listings, external sources, and vector embeddings
│   │   ├── Resume.php                  # Parsed resumes and candidate vector embeddings
│   │   └── User.php                    # User accounts with scoped application relationships
│   ├── Observers/
│   │   ├── JobVacancyObserver.php      # Auto-generates OpenAI embeddings on create/update
│   │   └── ResumeObserver.php          # Auto-generates candidate embeddings on resume upload
│   └── Support/
│       ├── EmbeddingText.php           # Rich text serialization for OpenAI embeddings
│       ├── JobFilter.php               # Negative keywords and non-tech stack exclusions
│       └── SkillMatcher.php            # Hybrid AI matching engine (Vectors + Keywords)
├── composer.json
└── README.md
```

---

## 🎯 Key Domain Logic & Features

### 1. Dual Application Model (`JobApplication`)
Supports both standard platform applicants and personal job hunter tracking:
- **`is_personal`**: Boolean flag cleanly separating platform client submissions from personal applications.
- **Scopes**: `scopePersonal()` and `scopeClientApplications()`.
- **Lifecycle Helpers**: `markAsApplied()`, `markAsInterviewing()`, `markAsOffered()`, `markAsRejected()`, `markAsWithdrawn()`, and `appendNotes()`.
- **Tailored AI Fields**: `tailored_cover_letter`, `tailored_key_points`, `suggested_subject_line`, and `hunter_status`.

### 2. Hunter Application Lifecycle (`HunterApplicationStatus`)
Type-safe enum managing personal application progression:
- `Draft` (`gray`)
- `Applied` (`blue`)
- `Interviewing` (`amber`)
- `Offered` (`emerald`)
- `Rejected` (`rose`)
- `Withdrawn` (`zinc`)

### 3. Hybrid AI Match Engine (`SkillMatcher`)
An advanced multi-layer algorithm that evaluates candidate-job fit:
1. **Semantic Vector Similarity (70% weight)**: Cosine similarity between candidate embedding and vacancy embedding.
2. **Strict Skill Keyword Matching (30% weight)**: Word-boundary keyword inspection of core technical stacks.
3. **Experience & Seniority Calibration**: Detects Junior, Mid, Senior, and Lead/Management requirements with penalties for seniority mismatches.
4. **Hard Stack Incompatibility Filters**: Identifies fundamental tech stack mismatches (e.g., C++ embedded/firmware, Java/Spring, mobile-only) and applies calibrated penalties.

### 4. Smart Role Exclusions (`JobFilter`)
Maintains database purity by discarding irrelevant vacancies before import:
- Excludes non-technical positions (Sales, Marketing, HR, Finance, Legal, Customer Support).
- Filters out non-matching specialized roles (Hardware, Firmware, Embedded Systems, Semiconductor, DSP, Linux Kernel).

---

## ⚙️ Installation & Usage

This library is included via Composer VCS in dependent applications.

### Composer Configuration (`composer.json`)

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/Ammar-1993/job-shared.git",
        "options": {
            "symlink": true
        }
    }
],
"require": {
    "job/shared": "*"
}
```

> **Note**: Autoloading maps the `App\` namespace directly to `src/` to allow transparent drop-in usage across Laravel applications.

### Code Examples

```php
use App\Models\JobVacancy;
use App\Models\JobApplication;
use App\Enums\HunterApplicationStatus;
use App\Support\SkillMatcher;

// 1. Querying imported external vacancies
$externalJobs = JobVacancy::imported()->active()->latest()->get();

// 2. Computing hybrid match score
$matchResult = SkillMatcher::computeHybridScore(
    $candidateEmbedding,
    $jobEmbedding,
    $candidateSkills,
    $jobVacancy->title,
    $jobVacancy->description,
    $candidateExperience
);

echo "Match Score: " . $matchResult['composite_score'] . "%";

// 3. Managing a personal job hunter application
$application = JobApplication::create([
    'job_vacancy_id'         => $jobVacancy->id,
    'user_id'                => $userId,
    'is_personal'            => true,
    'hunter_status'          => HunterApplicationStatus::Applied,
    'applied_channel'        => 'LinkedIn',
    'applied_at'             => now(),
    'tailored_cover_letter'  => $aiCoverLetter,
    'suggested_subject_line' => $aiSubject,
]);
```

---

## 🌐 Live Platform URLs

| Service | Environment | URL |
| :--- | :--- | :--- |
| **Job App** (Candidate Portal & Hunter) | Production | [hireme-platform.online](https://hireme-platform.online) |
| **Job Backoffice** (Admin & Pipeline) | Production | [admin.hireme-platform.online](https://admin.hireme-platform.online) |

---

## 🔐 Default Credentials (Local Development)

When seeding a local development database via `php artisan migrate --seed`:

- **Role**: Super Admin
- **Email**: `admin@admin.com`
- **Password**: `12345678`

> ⚠️ **Security Warning**: Never use default credentials in staging or production. Change the administrator password immediately upon initial deployment.

---

<p align="center">Developed with ❤️ by Eng. Ammar Al-Najjar</p>
