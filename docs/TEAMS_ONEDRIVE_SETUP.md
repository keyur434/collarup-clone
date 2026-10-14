# Teams meeting URL → stored video → AI report

Two paths. Both need the **Sync app** (not the SSO login app).

## Azure: Sync app registration

1. New app registration → **Sync app** (separate from login).
2. **Certificates & secrets** → client secret → `.env` `AZURE_AD_SYNC_CLIENT_SECRET`.
3. **API permissions** → **Application** (not Delegated) → admin consent:

| Permission | Used for |
|------------|----------|
| `Files.ReadWrite.All` | Upload/list videos in OneDrive folder |
| `Sites.Read.All` | SharePoint document library drives |
| `OnlineMeetings.Read.All` | Find meeting by join URL |
| `OnlineMeetings.ReadWrite` (application) | Create Teams meeting when HR schedules |
| `OnlineMeetingRecording.Read.All` | Download Teams cloud recording |
| `Calendars.Read` (application) | Optional: resolve Keka/Outlook calendar Teams links |

**Teams admin (required for recording download):** Without this, `getAllRecordings` may work but `/recordings/.../content` returns:

`Application is not allowed to perform operations on the user '…'`

Use the **Sync app** client ID from `.env` (`AZURE_AD_SYNC_CLIENT_ID`), not the SSO login app.

```powershell
# Teams PowerShell (Connect-MicrosoftTeams as Global/Teams admin)
New-CsApplicationAccessPolicy -Identity "CollarUp-Graph" `
  -AppIds "<AZURE_AD_SYNC_CLIENT_ID>" `
  -Description "TalentPulse Graph sync — meeting recordings"

# One recruiter
Grant-CsApplicationAccessPolicy -PolicyName "CollarUp-Graph" -Identity organizer@yourcompany.com

# Or all users in tenant
# Grant-CsApplicationAccessPolicy -PolicyName "CollarUp-Graph" -Global
```

Wait up to ~30 minutes, then retry `php scripts/fetch_teams_recording_now.php <interview-id>`.

**Windows server:** install [FFmpeg](https://ffmpeg.org) and set in `.env`:

```env
FFMPEG_PATH=C:\ffmpeg\bin\ffmpeg.exe
```

(no quotes around the path)

4. `.env`:

```env
AZURE_AD_TENANT_ID=...
AZURE_AD_SYNC_CLIENT_ID=...
AZURE_AD_SYNC_CLIENT_SECRET=...

ONEDRIVE_DRIVE_ID=...          # see below
ONEDRIVE_FOLDER_PATH=InterviewRecordings

TEAMS_ORGANIZER_USER_ID=hr@yourcompany.com
TEAMS_SHARED_ORGANIZER=true          # portal schedules on behalf of hr@ — recruiters never log into hr@
TEAMS_AUTO_CREATE_MEETINGS=true
```

### Get `ONEDRIVE_DRIVE_ID`

```bash
php scripts/resolve_onedrive_drive.php hr@yourcompany.com
```

Or SharePoint team site:

```bash
php scripts/resolve_onedrive_drive.php --site https://yourtenant.sharepoint.com/sites/YourSite
```

Create folder **`InterviewRecordings`** in that drive (name must match `ONEDRIVE_FOLDER_PATH`).

### Verify

```bash
php scripts/test_integrations.php graph
php cron/sync_recordings.php
```

---

## Path A — HR schedules in TalentPulse (recommended)

**HR steps:** Job → Interviews → **Schedule** → date/time → **Save & send invite**. No paste link, no upload.

**What happens**

1. Graph creates a Teams meeting under the **logged-in recruiter’s** Microsoft account (parallel interviews OK — hr@ is not on every call).
2. Candidate email includes the join link; `teams_meeting_id` + organizer are stored on the interview.
3. After the slot ends, `queue_worker.php` polls and queues `FetchTeamsRecording` (retries until recording exists).
4. Recording → OneDrive → `AnalyzeInterview` → report email.

**Requirements (Microsoft side)**

- Recruiters sign in to TalentPulse with their **work email** (same tenant as the sync app).
- Teams **cloud recording** enabled (auto-record for organizers is ideal).
- Sync app permission **OnlineMeetings.ReadWrite** (application) + admin consent.
- `TEAMS_ORGANIZER_USER_ID` only as fallback when scheduler has no Azure-linked email.

**Cron (required)**

```bash
php cron/queue_worker.php    # every 1 minute (Task Scheduler)
```

Re-save the Teams link or re-queue if the first fetch ran before the recording existed.

**If auto-fetch is not configured**

- HR can still paste the link for reference and **upload the MP4 file** manually on the same form.

---

## Path B — Recordings land in a shared OneDrive folder

Use when Teams saves files to a library HR controls, or someone copies recordings into a shared folder.

1. Teams / Stream exports land in OneDrive (or HR copies `.mp4` into `InterviewRecordings`).
2. Scheduled task:

```bash
php cron/sync_recordings.php   # e.g. every 15 minutes
```

3. App → **Pending Assignment** → assign file to candidate interview → **Assign & Analyze**.

---

## Your `sync_recordings` HTTP 400 error

Typical causes:

| Cause | Fix |
|-------|-----|
| **Unencoded spaces in Graph URL** | Fixed in code — redeploy `OneDriveStorageService.php` |
| **Wrong `ONEDRIVE_DRIVE_ID`** | Use `resolve_onedrive_drive.php`; not a web URL, not login app id |
| **Folder missing** | Create `InterviewRecordings` in that drive |
| **Sync app permissions** | Application permissions + admin consent |
| **IIS HTML 400** | Bad URL shape — run `php scripts/test_integrations.php graph` after fix |

---

## Quick checklist

- [ ] Sync app + admin consent (4 permissions above)
- [ ] `ONEDRIVE_DRIVE_ID` + folder created
- [ ] `TEAMS_ORGANIZER_USER_ID` = organizer mailbox
- [ ] `php scripts/test_integrations.php graph` → folder list OK
- [ ] Task Scheduler: `queue_worker.php` every 1 min
- [ ] Optional: `sync_recordings.php` every 15 min
- [ ] Teams cloud recording policy on for interviews

---

## Manual fallback (no Graph)

Report page → upload `.mp4` directly. Files go to local `storage/videos/` until OneDrive is configured.
