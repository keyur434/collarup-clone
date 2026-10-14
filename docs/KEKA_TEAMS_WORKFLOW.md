# Keka + Teams join link (no Keka Hire API)

## Recruiter steps

1. Add candidate to the job in TalentPulse (if not already there).
2. Schedule the interview in **Keka** and send the Teams invite as today.
3. In TalentPulse → job → **Interviews** → **Link Teams meeting**.
4. Paste the **join link** from Keka, date/time, and **host work email** (who owns the meeting in Teams).
5. After the interview (with cloud recording), wait for the report — no upload.

## `.env` (recommended)

```env
TEAMS_KEKA_REGISTER_MODE=true
TEAMS_AUTO_CREATE_MEETINGS=false
TEAMS_SHARED_ORGANIZER=false
SHOW_MANUAL_VIDEO_UPLOAD=false
```

Graph sync app + `queue_worker.php` every minute still required.

## Links

- Use **Teams join** URL (`meetup-join`), not **meeting recap** / recording share links.
- Host email must be the M365 user who **organized** the meeting (usually the recruiter who scheduled in Keka).
