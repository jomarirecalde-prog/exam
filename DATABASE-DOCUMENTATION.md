# Database Documentation

## Overview

The schema uses a normalized relational design with **exam versioning** and **immutable attempt snapshots** to preserve historical integrity.

## Entity Groups

### Authentication & RBAC
| Table | Purpose |
|-------|---------|
| `users` | All system users (extended with lockout, 2FA fields) |
| `login_logs` | Login success/failure audit |
| `roles`, `permissions` | Spatie Permission RBAC |
| `personal_access_tokens` | Sanctum API tokens for sync |

### Academic Structure
| Table | Purpose |
|-------|---------|
| `academic_years` | e.g. 2026-2027 |
| `semesters` | 1st Semester, 2nd Semester |
| `departments` | CCIS, etc. |
| `programs` | BSIS, BSCS |
| `year_levels` | 1st Year, 2nd Year |
| `sections` | BSIS 1A |
| `subjects` | IS101 |
| `subject_section` | Subject offered to section |

### People
| Table | Purpose |
|-------|---------|
| `students` | Student profile linked to user |
| `instructors` | Instructor profile linked to user |
| `student_sections` | Enrollment records |
| `subject_instructor` | Teaching assignments |

### Questions
| Table | Purpose |
|-------|---------|
| `questions` | All question types with JSON `correct_answer` |
| `question_choices` | MCQ options |
| `question_banks` | Reusable banks |
| `question_bank_questions` | Bank membership |
| `topics`, `learning_objectives`, `question_categories` | Classification |

### Examinations
| Table | Purpose |
|-------|---------|
| `examinations` | Exam metadata, period (PRELIM/MIDTERM/FINAL) |
| `examination_versions` | Version history — edits create new versions |
| `examination_settings` | Randomization, timer, anti-cheat config |
| `examination_questions` | Questions per version |
| `exam_schedules` | Availability windows |
| `examination_assignments` | Student assignment |

### Attempts & Answers
| Table | Purpose |
|-------|---------|
| `examination_attempts` | UUID, status, timer, sync status |
| `attempt_question_snapshots` | Immutable question/choice order per attempt |
| `student_answers` | JSON answer payload |
| `essay_answers` | Manual grading for subjective items |
| `exam_activity_logs` | Tab switch, fullscreen exit, etc. |

### Grading & Results
| Table | Purpose |
|-------|---------|
| `grading_formulas` | Configurable grading rules |
| `grading_formula_rules` | Formula rule JSON config |
| `grades` | Final results with release status |
| `grade_overrides` | Audited score changes |

### Sync & Audit
| Table | Purpose |
|-------|---------|
| `sync_queue` | Pending offline records |
| `sync_logs` | Sync attempt history |
| `audit_logs` | System-wide audit trail |
| `notifications` | In-app notifications |
| `backups` | Backup history |
| `system_settings` | Institution configuration |

## Key Constraints

- `examination_attempts.uuid` — globally unique for sync
- `student_answers.uuid` — idempotent answer sync
- `(examination_id, student_id, attempt_number)` — unique attempts
- `(entity_type, entity_uuid)` on `sync_queue` — no duplicate sync

## Exam Versioning Flow

```
Instructor edits exam after attempts exist
  → New examination_version created (v2)
  → Existing attempts remain on v1 snapshots
  → New attempts use v2
```

## Indexes

Critical indexes are on:
- `(examination_id, status)` on attempts
- `(student_id, status)` on attempts
- `(subject_id, type, difficulty)` on questions
- `(sync_status, created_at)` on sync_queue

## ER Diagram (Simplified)

```
users ── students ── examination_attempts ── student_answers
  │                      │
  └── instructors        └── attempt_question_snapshots
         │
         └── examinations ── examination_versions ── examination_questions
                                    │
                              questions ── question_choices
```
