# Examination Guide

## For Instructors

### Creating an Examination (Wizard — coming in Phase 6 UI)

1. **Step 1 — Information:** Title, subject, section, period (Prelim/Midterm/Final), duration, passing score
2. **Step 2 — Settings:** Randomization, timer, anti-cheat, attempts
3. **Step 3 — Questions:** Create new, import CSV, or select from question bank

### Exam Lifecycle

```
DRAFT → SCHEDULED → PUBLISHED → ACTIVE → CLOSED → ARCHIVED
```

| Status | Meaning |
|--------|---------|
| DRAFT | Being prepared, not visible to students |
| SCHEDULED | Date/time set |
| PUBLISHED | Visible in student list |
| ACTIVE | Students can take the exam now |
| PAUSED | Temporarily suspended |
| CLOSED | No new attempts |
| ARCHIVED | Historical record |

### Question Types Supported

1. Multiple Choice
2. Multiple Select
3. True or False
4. Identification
5. Short Answer (manual grading)
6. Essay (manual grading)
7. Matching Type
8. Enumeration
9. Fill in the Blank

### Grading

- **Objective questions:** Auto-graded by `GradingEngine` on submit
- **Essay/Short Answer:** Instructor grades manually; final score recalculates automatically
- **Release results:** Students see scores only when `is_released = true`

### Monitoring Active Exams

The monitoring dashboard shows:
- Total / Started / In Progress / Submitted / Expired
- Per-student progress and suspicious activity count

---

## For Students

### Taking an Exam

1. Log in with **Student ID** or email
2. Open assigned exam from dashboard
3. Read instructions, click **Start**
4. Answer questions — autosaved locally and on server
5. Use question navigator to jump between items
6. Flag questions for review
7. Submit when finished (or auto-submit when time expires)

### Timer Rules

- Countdown is validated **server-side** via `expires_at`
- Refreshing the browser does **not** reset the timer
- Warnings at 10, 5, and 1 minute remaining

### Viewing Results

Results appear only when released by the instructor. If enabled, you may see:
- Score and percentage
- Correct answers and explanations

---

## Examination Periods

| Period | Code |
|--------|------|
| Prelim | `PRELIM` |
| Midterm | `MIDTERM` |
| Final | `FINAL` |

---

## Anti-Cheating (Configurable)

The system logs suspicious events — it does not claim to fully prevent cheating:

- Tab switches
- Browser minimized
- Fullscreen exited
- Session changes
- Inactivity timeout

All events are recorded in `exam_activity_logs`.

---

## Import Questions (CSV Template)

| Column | Description |
|--------|-------------|
| question | Question text |
| type | `multiple_choice`, `true_false`, etc. |
| choice_a – choice_d | Options |
| correct_answer | Correct value |
| points | Point value |
| topic | Topic name |
| difficulty | easy / medium / hard |

---

## Attempt Statuses

| Status | Description |
|--------|-------------|
| NOT_STARTED | Assigned but not begun |
| IN_PROGRESS | Currently taking |
| SUBMITTED | Manually submitted |
| AUTO_SUBMITTED | Timer expired |
| EXPIRED | Past deadline |
| SYNC_PENDING | Offline, awaiting sync |
| SYNCED | Synced to central server |
