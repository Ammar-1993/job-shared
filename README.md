# Job Shared Library

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)

## 📖 Introduction

The **Job Shared Library** is a core component of the **Job Vacancies Platform**. It encapsulates the shared logic, data structures, and domain entities used across the ecosystem, specifically by the **Job App** (Candidate Portal) and **Job Backoffice** (Admin Dashboard).

By centralizing these definitions, we ensure consistency, reduce code duplication, and simplify maintenance across the micro-services.

---

## 📂 Project Structure

This library primarily manages:

### 1. Eloquent Models
The definitive source of truth for database entities:
- **`User`**: Authentication and role management.
- **`Company`**: Employer profiles and data.
- **`JobVacancy`**: Job listings and details.
- **`JobCategory`**: Classification of jobs.
- **`JobApplication`**: Candidates' applications to specific jobs.
- **`Resume`**: Meta-data for candidate resumes.

### 2. Enums
Strictly typed enumerations to ensure data integrity:
- **`UserRole`**: Admin, Company Owner, Candidate.
- **`JobStatus`**: Active, Closed, Draft.
- **`JobType`**: Full-time, Remote, Contract, etc.
- **`ApplicationStatus`**: Pending, Reviewed, Rejected, Hired.

---

## ⚙️ Usage & Integration

This package is installed as a local path repository in the dependent projects.

### Composer Configuration
To use this library in `job-app` or `job-backoffice`, the `composer.json` includes:

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

### Example Usage

```php
use App\Models\JobVacancy;
use App\Enums\JobStatus;

// Querying shared models
$jobs = JobVacancy::where('status', JobStatus::Active)->get();
```

---

## 🤝 Contribution

1. **Fork** the repository.
2. **Clone** it locally along with the main applications.
3. Make changes to Models or Enums.
4. **Push** updates and run `composer update` in the dependent projects.

---

<p align="center">Developed by ❤️ Engineer Ammar Al-Najjar</p>
