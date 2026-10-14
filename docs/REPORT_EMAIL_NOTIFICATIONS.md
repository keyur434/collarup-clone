# Report-ready email notifications

When AI analysis finishes (`AnalyzeInterviewJob` → `NotificationService::notifyReportReady`), the app sends **one email per report**:

| Field | Recipients |
|--------|------------|
| **To** | All job interviewers (portal + `job_external_contacts`) |
| **CC** | All hiring managers + the application’s assigned recruiter |

The email includes candidate name, current/expected CTC (on the application), interview score, and a **shared magic link** (`/reports/shared/{token}`) to the read-only report. Opening the link stores a session grant for video playback without portal login.

Template: `views/emails/report_ready.php` (edit for layout/copy revisions).

CTC fields: **Manage candidate** → Application section (`current_ctc`, `expected_ctc`). Migration `020_application_ctc.sql`.

Portal **owner / hr_head / member** users who are also on the job keep normal signed-in access to jobs and reports; magic links do not remove those rights.

## Setup

1. Run migration `database/migrations/019_interview_report_shares.sql` (or `php database/migrate.php`).
2. Configure SMTP in `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM`).
3. Optional: disable emails with app setting `notify_report_ready` = `0` (Settings or `app_settings` table).

Emails are logged in `notification_log` with type `report_ready`.

## Test vs live (on server)

Set in production `.env` while validating:

```env
NOTIFY_EMAIL_MODE=test
NOTIFY_EMAIL_TEST_TO=your.email@company.com
```

- **test** — SMTP sends to `NOTIFY_EMAIL_TEST_TO` only; subject `[TEST]`; body shows the real intended recipient.
- **log** — no SMTP; `notification_log.status` = `dry_run` (safe during analysis runs).
- **live** — real hiring manager / interviewer addresses.

When ready for production:

```env
NOTIFY_EMAIL_MODE=live
```

### CLI (run on Apache server where PHP exists)

```bash
cd /path/to/collarup-clone
php database/migrate.php
php scripts/test_report_notifications.php status
php scripts/test_report_notifications.php list
php scripts/test_report_notifications.php preview --interview=<interview-uuid>
php scripts/test_report_notifications.php send --interview=<interview-uuid>
php scripts/test_report_notifications.php send --interview=<interview-uuid> --mode=test
```

Use `preview` to print magic links without email. Use `send` to exercise the full pipeline (respects `NOTIFY_EMAIL_MODE` unless you pass `--mode=`).

After a test email, open the **View interview report** link in a private window to confirm read-only shared access and video playback.
