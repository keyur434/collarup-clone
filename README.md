# CollarUp Clone

AI-powered recruitment platform — PHP 7.3.4, MSSQL, **OneDrive/Graph**, ElevenLabs STT, Azure OpenAI.

## Requirements

- PHP 7.3.4+ (pdo_sqlsrv, curl, openssl, mbstring, json, fileinfo)
- Microsoft SQL Server 2016+
- ffmpeg (audio extraction for STT only — no video compression)
- IIS or Apache with URL rewriting
- Windows Task Scheduler (queue worker + optional recording sync)

## Quick Start

### 1. Database

```powershell
php E:\collarup-clone\database\install.php
```

Existing DB: `php E:\collarup-clone\database\migrate.php`

### 2. Environment

Copy `.env.example` → `.env` and configure:

| Area | Variables |
|------|-----------|
| MSSQL | `DB_*` |
| SSO (login app) | `AZURE_AD_TENANT_ID`, `AZURE_AD_LOGIN_CLIENT_ID`, `AZURE_AD_LOGIN_CLIENT_SECRET`, `AZURE_AD_LOGIN_REDIRECT_URI` |
| Graph sync app | `AZURE_AD_SYNC_CLIENT_ID`, `AZURE_AD_SYNC_CLIENT_SECRET` |
| OneDrive | `ONEDRIVE_DRIVE_ID`, `ONEDRIVE_FOLDER_PATH` |
| Teams fetch | `TEAMS_ORGANIZER_USER_ID` (email or Azure object ID) |
| AI | `AZURE_OPENAI_*`, `ELEVENLABS_API_KEY` |

Without Graph keys, videos fall back to `storage/videos/` locally.

### 3. Azure Portal (split apps)

**Login app:** Redirect URI `https://your-app/login/callback`, delegated permissions `openid profile email User.Read`.

**Sync app:** Application permissions `Files.ReadWrite.All`, `Sites.Read.All`, `OnlineMeetings.Read.All`, `OnlineMeetingRecording.Read.All` + admin consent.

### 4. Scheduled tasks

```
php E:\collarup-clone\cron\queue_worker.php      # every 1 min
php E:\collarup-clone\cron\sync_recordings.php    # every 15–30 min (optional)
```

### 5. Workflows

**Upload:** Video → OneDrive (or local) → ElevenLabs STT → Azure OpenAI report.

**Teams URL:** Saved → `FetchTeamsRecording` job → copy to OneDrive → analyze.

**Pending Assignment (FRD):** `sync_recordings.php` imports new files → **Pending Recordings** → assign to interview.

### 6. Auth

- **Pre-provision only:** Add users in Team Management before Microsoft SSO.
- Local demo: `admin@company.local` / `admin123`

## Roles

| Role | Access |
|------|--------|
| `owner` | Full + team management |
| `hr_head` | All jobs, pending assignment, edit reports |
| `member` | Assigned jobs/interviews only; view reports |
