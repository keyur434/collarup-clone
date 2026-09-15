# CollarUp Clone — Implementation Roadmap

> **Living document.** Update this file whenever a phase, task, or iteration changes.
> Last updated: **2026-09-14**

## How to use

1. Pick the active phase below.
2. Work only on tasks marked `in_progress` or `pending` in that phase.
3. When a task ships, check it off and add a line to **Iteration log**.
4. If scope changes, edit the phase — do not start a parallel doc.

## Current status

| Phase | Status | Target |
|-------|--------|--------|
| **1 — Multi-pass AI** | **in_progress** | GPT-4.1 + Q&A extract + scoring passes |
| 2 — Resume & JD match | pending | CV extract + summary cache |
| 3 — Pool compare | pending | Relative rank vs applicant pool |
| 4 — Report UI & export | pending | Surface enhanced JSON on report/export |
| 5 — Interviewer profiles | pending | Profile entity + match pass |
| 6 — STT scale-up | pending | 90-min chunking + optional Sarvam |
| 7 — Advanced proctoring | optional | Webcam, copy-paste, screen events |

**Production cost baseline:** ~₹105 / 90-min interview (ElevenLabs + Azure Mumbai GPT-4.1 multi-pass).

---

## Architecture target (end state)

```
Upload → Queue → STT (ElevenLabs | Sarvam)
  → Refine (gpt-4.1-mini)
  → Pass 1: Q&A extraction (gpt-4.1-mini, no judgment)
  → Pass 2: Rubric + integrity + phases (gpt-4.1)
  → Pass 3: Resume/JD match (gpt-4.1)          [Phase 2]
  → Pass 4: Pool compare (gpt-4.1)             [Phase 3]
  → Pass 5: Interviewer alignment (gpt-4.1)    [Phase 5]
  → Merge → interview_reports + artifacts
  → Notify
```

---

## Phase 1 — Multi-pass AI (GPT-4.1)

**Goal:** Replace single monolithic `analyzeInterview()` with auditable passes. No resume/pool yet.

**Status:** in_progress  
**Est. effort:** 3–5 days  
**Prompt version:** `v2-multipass`

### Tasks

- [x] Create `docs/IMPLEMENTATION_ROADMAP.md`
- [x] Migration `006_multipass_analysis.sql` — `ai_analysis_passes` table
- [x] `InterviewAnalysisOrchestrator` service — run tracking, merge, error handling
- [x] `AzureOpenAIService::extractQaPairs()` — judgment-free Q&A pass (gpt-4.1-mini)
- [x] `AzureOpenAIService::analyzeScoring()` — rubric, overview, integrity, phases (gpt-4.1)
- [x] Refactor `AnalyzeInterviewJob` to use orchestrator
- [x] Default `AZURE_OPENAI_DEPLOYMENT=gpt-4.1`
- [x] Wire `ai_analysis_runs` + per-pass rows in `ai_analysis_passes`
- [ ] Deploy `gpt-4.1` + `gpt-4.1-mini` in Azure South India (portal — ops)
- [ ] Re-run analysis on 2–3 real 90-min interviews and validate Q&A vs scores
- [ ] Tune prompts if Q&A misses speaker turns on Hinglish

### Files touched (Phase 1)

| File | Change |
|------|--------|
| `app/Services/InterviewAnalysisOrchestrator.php` | New orchestrator |
| `app/Services/AzureOpenAIService.php` | Multi-pass prompts + deployments |
| `app/Jobs/AnalyzeInterviewJob.php` | Calls orchestrator |
| `config/services.php` | Pipeline + deployment defaults |
| `database/migrations/006_multipass_analysis.sql` | Pass audit table |
| `.env.example` | New env vars |

### Env vars (Phase 1)

```env
AZURE_OPENAI_DEPLOYMENT=gpt-4.1
AZURE_OPENAI_EXTRACTION_DEPLOYMENT=gpt-4.1-mini
AZURE_OPENAI_REFINE_DEPLOYMENT=gpt-4.1-mini
ANALYSIS_PIPELINE=multipass   # legacy | multipass
```

### Rollback

Set `ANALYSIS_PIPELINE=legacy` to restore single-pass `analyzeInterview()` behavior.

---

## Phase 2 — Resume & JD match

**Goal:** Extract resume text on upload, cache summaries, score applicant vs JD.

**Status:** pending  
**Est. effort:** 4–6 days  
**Blocked by:** nothing (can start after Phase 1 stable)

### Tasks

- [ ] Migration — `cv_summaries` table
- [ ] `CvExtractionService` — PDF text extract (DOC/DOCX TBD)
- [ ] Trigger summary job on resume upload / replace
- [ ] `AzureOpenAIService::analyzeResumeJdMatch()` pass
- [ ] Populate `applications.match_score_grade` + `resume_match_score`
- [ ] Show match badge on applicant pool + report

### Schema (planned)

```sql
cv_summaries (id, candidate_id, source_file_hash, extracted_text,
              summary_json, model_name, created_at, updated_at)
```

---

## Phase 3 — Pool compare

**Goal:** Relative rank vs other applicants on the same job using cached CV summaries.

**Status:** pending  
**Est. effort:** 5–7 days  
**Depends on:** Phase 2

### Tasks

- [ ] `AzureOpenAIService::analyzePoolCompare()` pass
- [ ] Load peer summaries (cap 25) in orchestrator
- [ ] Store `pool_rank`, `pool_compare_json` on application or report
- [ ] Upgrade `CompareController` / compare view with AI rationale
- [ ] Skip compare when &lt; 3 applicants on job

---

## Phase 4 — Report UI & export

**Goal:** Show data already in `raw_analysis_json` + new match/pool fields.

**Status:** pending  
**Est. effort:** 2–3 days

### Tasks

- [ ] Report tabs: conversation phases, topic adherence, completeness, key questions
- [ ] Export template includes enhanced fields + match scores
- [ ] Optional: true PDF (Dompdf/wkhtmltopdf)

---

## Phase 5 — Interviewer profiles

**Goal:** Match interview against assigned interviewer seniority/style.

**Status:** pending  
**Est. effort:** 5–8 days

### Tasks

- [ ] Migration — `interviewer_profiles`
- [ ] Settings or team UI for profile CRUD
- [ ] `analyzeInterviewerMatch()` pass in orchestrator
- [ ] Report section for interviewer alignment

---

## Phase 6 — STT scale-up (90-min)

**Goal:** Reliable transcription for 90-minute Hinglish interviews.

**Status:** pending  
**Est. effort:** 3–5 days

### Tasks

- [ ] `SttProviderInterface` + ElevenLabs adapter refactor
- [ ] Audio chunking in PHP (ffmpeg segments, merge transcripts)
- [ ] Optional `SarvamService` (local reference script — not in repo)
- [ ] A/B test ElevenLabs vs Sarvam on 10 real recordings
- [ ] `STT_PROVIDER=elevenlabs|sarvam` config

---

## Phase 7 — Advanced proctoring (optional)

**Goal:** Beyond tab-switch logging.

**Status:** optional  
**Est. effort:** 10+ days

### Tasks

- [ ] Copy/paste events on join page
- [ ] Webcam presence sampling (privacy policy required)
- [ ] External display detection (limited browser support)
- [ ] Integrity score model combining events + AI signals

---

## Already built (do not re-implement)

| Area | Notes |
|------|-------|
| Auth + Azure AD SSO | Code complete; needs portal `.env` |
| OneDrive / Graph / Teams fetch | Code complete; needs `.env` |
| Queue pipeline | `ProcessInterviewMedia`, `AnalyzeInterview`, `FetchTeamsRecording` |
| Phase 2 notifications | Mail + retention + Keka cache + join integrity events |
| Compare UI (manual) | Side-by-side; no AI pool pass yet |
| Report export | HTML print; not PDF |

---

## Ops checklist (every deploy)

```bash
php database/migrate.php
php cron/queue_worker.php   # every 1 min
```

Ensure Azure deployments exist: `gpt-4.1`, `gpt-4.1-mini`.

---

## Iteration log

| Date | Phase | Change |
|------|-------|--------|
| 2026-09-14 | — | Roadmap created from product + cost discussions |
| 2026-09-14 | 1 | Started: orchestrator, multi-pass prompts, migration 006, gpt-4.1 defaults |
| 2026-09-14 | 1 | Foundry v1 chat API support; `scripts/test_integrations.php`; `docs/AZURE_ENV_CHECKLIST.md` |
| 2026-09-14 | — | Removed legacy `AZURE_STORAGE_*`, `AzureBlobService`, unused `ONEDRIVE_SITE_ID` |

---

## Cost reference (management)

See conversation summary; planning figure **₹105 / 90-min interview** at USD×96 ElevenLabs + Azure Mumbai INR.

---

## Related files

- `AGENTS.md` — repo orientation for AI agents
- Local-only Python reference pipeline (Sarvam, chunking) — gitignored, not in repo
- `config/services.php` — external service configuration
