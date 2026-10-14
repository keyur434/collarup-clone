# Paid & metered external services

TalentPulse only calls these when the related feature runs. **No charge on page load** except where noted.

## Azure OpenAI (primary AI cost)

**Billing:** Per input/output tokens on your Azure subscription.

| Feature | When it runs | Typical deployment env |
|--------|----------------|-------------------------|
| Interview analysis (multi-pass) | After recording is processed / requeue | `AZURE_OPENAI_DEPLOYMENT`, `AZURE_OPENAI_EXTRACTION_DEPLOYMENT` |
| Transcript refine (optional) | Before analysis if `REFINE_TRANSCRIPT=true` | `AZURE_OPENAI_REFINE_DEPLOYMENT` |
| **Hiring desk — Score resumes vs JD** | HR clicks button on job desk | `AZURE_OPENAI_DEPLOYMENT` (cached per resume hash in `cv_summaries`) |
| **Hiring desk — Generate brief** | HR clicks Generate brief | `AZURE_OPENAI_DEPLOYMENT` (cached in `job_hiring_insights`) |
| **Hiring desk — Pool rank** | HR clicks Generate pool rank | `AZURE_OPENAI_DEPLOYMENT` (batched calls) |

**Config:** `.env` `AZURE_OPENAI_*` — deployment name must match **Azure Portal → Deployments → name**, not the model label `gpt-4.1`.

**Verify:** `php scripts/test_integrations.php openai`

**Rough baseline (from roadmap):** ~₹105 / 90‑min interview with ElevenLabs + Azure multi-pass (your tariffs may differ).

## ElevenLabs (speech-to-text)

**Billing:** Per audio minute / character plan.

| Feature | When it runs |
|--------|----------------|
| Interview transcription | Queue job after recording is available |

**Config:** `ELEVENLABS_API_KEY`, `ELEVENLABS_STT_MODEL`, etc.

## Microsoft Graph / Teams / OneDrive

**Billing:** Usually included in Microsoft 365 / Azure AD; not a separate TalentPulse line item.

| Feature | When it runs |
|--------|----------------|
| SSO login | User signs in with Microsoft |
| Teams meeting link / recording fetch | Schedule, sync recording |
| OneDrive / shared library | Recording storage paths |

**Config:** `AZURE_AD_*`, `ONEDRIVE_*`, `TEAMS_*`

## SMTP (optional)

**Billing:** Your mail provider.

| Feature | When it runs |
|--------|----------------|
| Notifications | Decision / workflow emails if enabled |

**Config:** `SMTP_*`

## Keka (optional)

**Billing:** Per your Keka contract.

| Feature | When it runs |
|--------|----------------|
| HRIS integration | If `TEAMS_KEKA_REGISTER_MODE` / Keka APIs are wired |

**Config:** `KEKA_*`

## What does *not* cost API money

- Browsing jobs, candidates, reports, pipeline, leaderboard (reads SQL only)
- Hiring desk table, weights, decision notes, committee HTML export (no AI)
- Cached hiring brief / CV match (re-run only when input changes or HR clicks again)
