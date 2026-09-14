# CollarUp Clone — Agent Context

> Feed this file at the start of a new chat for fast repo familiarization.
> Last updated: 2026-09-14.

**Implementation roadmap:** `docs/IMPLEMENTATION_ROADMAP.md` — update when phases change.

## What This Is

AI-powered recruitment platform (CollarUp clone). PHP 7.3.4+ MVC app with MSSQL, Microsoft SSO, OneDrive/Graph for video storage, ElevenLabs STT, Azure OpenAI for interview analysis.

**Not** a framework — custom lightweight MVC. No Composer, no ORM, no Models layer.

## Tech Stack

| Layer | Choice |
|-------|--------|
| Runtime | PHP 7.3.4+ (`pdo_sqlsrv`, curl, openssl, mbstring, json, fileinfo) |
| Database | Microsoft SQL Server 2016+ |
| Web server | IIS or Apache (URL rewrite via `.htaccess` / `web.config`) |
| Frontend | Server-rendered PHP views, vanilla JS, custom CSS (Inter font) |
| Video storage | OneDrive via Microsoft Graph (fallback: `storage/videos/`) |
| STT | ElevenLabs Scribe v2 |
| AI analysis | Azure OpenAI multi-pass (`gpt-4.1` scoring + `gpt-4.1-mini` Q&A extract) via `InterviewAnalysisOrchestrator` |
| Audio extraction | ffmpeg (no video compression) |
| Background jobs | DB-backed queue (`job_queue` table), cron workers |
| Auth | Local password + Azure AD SSO (pre-provisioned users only) |

## Directory Layout

```
collarup-clone/
├── public/                  # Web root (index.php entry point)
│   ├── index.php            # All routes defined here
│   ├── assets/css|js/       # Static frontend
│   └── storage/video.php    # Local video streaming proxy
├── app/
│   ├── bootstrap.php        # Env, session, autoload, config
│   ├── Config/
│   │   ├── Database.php     # PDO singleton + db() helper
│   │   └── Router.php       # Simple regex router
│   ├── Controllers/         # 12 controllers (see Routes)
│   ├── Services/            # Business logic + external APIs
│   ├── Jobs/                # Queue job handlers (3 types)
│   ├── Middleware/          # AuthMiddleware only
│   └── Helpers/functions.php # Global helpers (view, url, csrf, etc.)
├── views/                   # PHP templates (dot notation → path)
│   ├── layouts/app.php      # Main shell (sidebar, nav)
│   ├── jobs/, candidates/, reports/, ...
│   └── partials/            # Reusable fragments
├── config/
│   ├── app.php              # App settings, score labels, upload limits
│   ├── database.php         # MSSQL connection
│   └── services.php         # Azure, OneDrive, OpenAI, ElevenLabs, SMTP
├── database/
│   ├── schema.sql           # Full schema (idempotent CREATE IF NOT EXISTS)
│   ├── seeds.sql            # Master data + default admin user
│   ├── install.php          # Fresh DB setup
│   ├── migrate.php          # Incremental migrations
│   └── migrations/          # 002–006 (see IMPLEMENTATION_ROADMAP.md)
├── cron/
│   ├── queue_worker.php     # Process job_queue (run every 1 min)
│   └── sync_recordings.php  # Import OneDrive recordings → pending (15–30 min)
└── storage/                 # Gitignored runtime dirs
    ├── videos/              # Local video fallback
    ├── resumes/             # Uploaded resumes
    ├── temp/                # Processing temp files
    └── logs/
```

## Request Lifecycle

1. `public/index.php` → `app/bootstrap.php`
2. Bootstrap: load `.env`, start session, autoload classes, load config
3. Router matches URI + method → runs middleware → invokes `Controller@method`
4. Controller queries via `db()`, calls services, renders with `render('view.name', $data)`
5. `render()` wraps view in `layouts.app` layout

## Key Helpers (`app/Helpers/functions.php`)

| Function | Purpose |
|----------|---------|
| `config('app.key')` | Load config file (dot notation) |
| `db()` | Database singleton (PDO) |
| `view()` / `render()` | Template rendering |
| `url()` / `asset()` | URL generation |
| `auth_user()` / `auth_check()` / `auth_id()` | Session auth |
| `csrf_token()` / `verify_csrf()` | CSRF protection |
| `flash()` | Session flash messages |
| `uuid()` | UUID v4 generation |
| `sql_page($limit, $offset)` | MSSQL OFFSET/FETCH pagination |
| `json_response()` | API JSON output |

## Routes (all in `public/index.php`)

**Auth:** `/login`, `/login/azure`, `/login/callback`, `/logout`

**Dashboard:** `/`, `/dashboard`

**Jobs (wizard):** `/jobs`, `/jobs/create` (+ pipeline, questions, draft-message steps), `/jobs/edit/{id}` (same steps), `/jobs/{id}/interviews`

**Reports:** `/jobs/{jobId}/reports/{reportId}` (+ decision, chat POST)

**Candidates:** `/candidates`, `/interviews` (same index), `/candidates/create`, `/applications/{id}/manage`, archive/restore/delete, `/interviews/{id}/details`, `/interviews/{id}/upload`

**Settings:** `/settings` (master data CRUD)

**Pipeline:** `/pipeline`

**Leaderboard:** `/leaderboard`

**Team:** `/team`, `/team/create`, `/team/edit/{id}`, `/team/delete/{id}`

**Pending recordings:** `/pending-recordings`, assign POST, `/api/pending/interviews`

**API:** `/api/master/departments|states|cities`, `/api/jobs/{id}/stages`, `/api/video/{interviewId}`

Protected routes use `['AuthMiddleware::handle']` middleware.

## Roles & Permissions (`PermissionService`)

| Role | Access |
|------|--------|
| `owner` | Full access + team management |
| `hr_head` | All jobs, pending recordings, edit reports, manage candidates |
| `member` | Only assigned jobs (hiring manager or interviewer); view reports |

Key methods: `canAccessJob()`, `canEditReport()`, `canManageCandidates()`, `canAssignPending()`, `canManageTeam()`, `requireJobAccess()`, `requireEditReport()`.

## Data Model (core entities)

```
users ──┬── job_hiring_managers ── jobs ──┬── job_pipeline_stages
        └── job_interviewers            ├── job_interview_questions
                                        ├── job_rubric_criteria
                                        └── job_email_templates
candidates ── applications ── interviews ──┬── interview_media
                                             ├── transcripts
                                             ├── ai_analysis_runs
                                             ├── interview_reports ──┬── interview_criterion_scores
                                             │                         └── interview_qa_notes
                                             ├── integrity_assessments
                                             ├── integrity_events
                                             └── team_chat_messages
pending_recordings (unassigned OneDrive imports)
job_queue (background jobs)
ingestion_watermarks (OneDrive sync state)
```

**IDs:** Jobs, candidates, applications, interviews, reports use `CHAR(36)` UUIDs. Users, master data use `INT IDENTITY`.

**Key statuses:**
- Application: `contacted|pending|scheduled|processing|ongoing|incomplete|completed|cancelled`
- Interview: `pending|scheduled|processing|ongoing|incomplete|completed|cancelled`
- Application decision: `none|advance|hold|reject|offer`
- Pipeline stage types: `applicant_pool|ai_interview|round|offer`

## Interview Processing Pipeline

### Flow 1: Manual video upload
```
Upload → storage/videos/ or temp
  → QueueService.push('ProcessInterviewMedia')
    → Upload to OneDrive (or keep local)
    → QueueService.push('AnalyzeInterview')
      → Download video → ElevenLabs STT (ffmpeg audio fallback)
      → GPT-4.1-mini transcript refine → save transcript
      → InterviewAnalysisOrchestrator (multipass):
          Pass 1: Q&A extraction (gpt-4.1-mini)
          Pass 2: Rubric scoring + integrity (gpt-4.1)
      → save report + scores + QA notes + ai_analysis_passes audit
      → Mark interview/application completed
```

### Flow 2: Teams meeting URL
```
Save meeting_url on interview
  → QueueService.push('FetchTeamsRecording')
    → Graph API: find meeting → download recording → OneDrive
    → QueueService.push('AnalyzeInterview') → (same as above)
```

### Flow 3: Pending recordings (FRD)
```
cron/sync_recordings.php → IngestionService.syncOneDriveFolder()
  → New files → pending_recordings table
  → HR assigns to interview via PendingRecordingController
  → Triggers analysis pipeline
```

**Queue jobs:** `ProcessInterviewMedia`, `AnalyzeInterview`, `FetchTeamsRecording`
**Worker:** `cron/queue_worker.php` (processes up to 10 jobs per run)

## Services

| Service | Purpose |
|---------|---------|
| `PermissionService` | Role-based access control |
| `InterviewWorkflowService` | Interview status transitions, queue helpers |
| `QueueService` | DB-backed job queue |
| `OneDriveStorageService` | Upload/download/list via Graph (fallback: local) |
| `MicrosoftGraphService` | Graph API client (`login` or `sync` app creds) |
| `AzureAdAuthService` | OAuth SSO for login app |
| `TeamsRecordingService` | Fetch Teams meeting recordings |
| `IngestionService` | Sync OneDrive folder → pending_recordings |
| `FFmpegService` | Audio extraction, duration |
| `ElevenLabsService` | Speech-to-text |
| `InterviewAnalysisOrchestrator` | Multi-pass analysis coordinator; logs `ai_analysis_runs` / `ai_analysis_passes` |
| `AzureOpenAIService` | STT refine, Q&A extraction, rubric scoring prompts; mock fallback if unconfigured |
| `ActivityLogService` | Audit logging |

## Azure Configuration (split apps)

**Login app** (delegated OAuth): `AZURE_AD_LOGIN_CLIENT_ID/SECRET`, redirect URI, permissions: `openid profile email User.Read`

**Sync app** (client credentials): `AZURE_AD_SYNC_CLIENT_ID/SECRET`, permissions: `Files.ReadWrite.All`, `Sites.Read.All`, `OnlineMeetings.Read.All`, `OnlineMeetingRecording.Read.All` + admin consent

**OneDrive:** `ONEDRIVE_DRIVE_ID`, `ONEDRIVE_FOLDER_PATH` (default `/InterviewRecordings`)

**Teams:** `TEAMS_ORGANIZER_USER_ID` (email or Azure object ID)

Without Graph keys → videos stored in `storage/videos/` locally.

## Environment Setup

```powershell
# 1. Copy and configure
cp .env.example .env

# 2. Fresh database
php database/install.php

# 3. Existing DB migrations
php database/migrate.php

# 4. Optional: full India locations
php database/seed_locations.php

# 5. Scheduled tasks (Windows Task Scheduler)
php cron/queue_worker.php       # every 1 min
php cron/sync_recordings.php    # every 15–30 min
```

**Default login:** `admin@company.local` / `admin123` (set `AUTH_ALLOW_LOCAL_LOGIN=true`)

## Coding Conventions

- **No Models** — controllers query `db()` directly with raw SQL
- **No Composer** — plain PHP classes, manual autoload in bootstrap
- **Views** use dot notation: `render('jobs.index')` → `views/jobs/index.php`
- **CSRF** — call `verify_csrf()` on all POST handlers; use `csrf_field()` in forms
- **Pagination** — MSSQL `ORDER BY ... OFFSET n ROWS FETCH NEXT m ROWS ONLY` via `sql_page()`
- **UUIDs** — `uuid()` for new entity IDs on jobs, candidates, applications, interviews, reports
- **Flash messages** — `flash('error', 'msg')` / `flash('success', 'msg')`
- **Activity logging** — `ActivityLogService::log($action, $entityType, $entityId)`
- **Permission checks** — always use `PermissionService` methods, not inline role checks
- **Storage fallback pattern** — services check `isConfigured()` and fall back to local filesystem
- **Timezone** — `Asia/Kolkata` (config/app.php)

## Frontend

- Layout: `views/layouts/app.php` — sidebar nav, responsive mobile toggle
- CSS: `public/assets/css/app.css` — purple brand (`#7c3aed`), Inter font
- JS: `public/assets/js/app.js` — sidebar toggle, alerts, drawer helpers
- Drawers: `views/partials/interview_schedule_drawer.php`, `interview_upload_drawer.php`
- Nav active state: pass `'activeNav' => 'jobs|candidates|dashboard|...'` to render

## Job Creation Wizard (4 steps)

1. **Basic info** — title, department, location, salary, skills (`/jobs/create`)
2. **Pipeline** — define stages (`/jobs/create/pipeline`)
3. **Questions** — interview questions (`/jobs/create/questions`)
4. **Draft message** — email templates (`/jobs/create/draft-message`)
5. Redirect to add candidates (`/jobs/create/candidates`)

Edit flow mirrors create with `/jobs/edit/{id}/...` routes. Job ID stored in session during wizard.

## Common Tasks for Agents

| Task | Where to look |
|------|---------------|
| Add a route | `public/index.php` |
| Add a page | Controller + `views/` template |
| Change permissions | `app/Services/PermissionService.php` |
| Modify interview pipeline | `app/Jobs/`, `app/Services/InterviewWorkflowService.php` |
| Change AI prompts | `app/Services/AzureOpenAIService.php` (passes), `InterviewAnalysisOrchestrator.php` (flow) |
| Track implementation phases | `docs/IMPLEMENTATION_ROADMAP.md` |
| Add DB column | New file in `database/migrations/`, run `migrate.php` |
| Change nav | `views/layouts/app.php` |
| API endpoints | `app/Controllers/ApiController.php` |
| Master data | `app/Controllers/SettingsController.php`, `database/seeds.sql` |

## Gotchas

- PHP 7.3.4 target — no typed properties, no arrow functions, limited null coalescing patterns
- MSSQL syntax: `TOP n`, `OFFSET/FETCH`, `SYSUTCDATETIME()`, `NVARCHAR(MAX)` for JSON
- Video storage: `OneDriveStorageService` (Graph) or local `storage/videos/` fallback — not Azure Blob
- `app/Models/` autoload path exists but folder is empty/unused
- Video playback: local files served via `/api/video/{interviewId}` (returns SAS-like URL) or `public/storage/video.php?f=`
- Users must be pre-provisioned in Team Management before Azure SSO works
- Queue worker must run via cron — jobs won't process otherwise
- `.env` is gitignored; never commit secrets
- `storage/*` dirs are gitignored except structure — create if missing

## File Count

~105 files total. 12 controllers, 14 services, 3 jobs, 1 middleware, ~30 view templates.
