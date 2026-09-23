# Manual transcript analysis — UX review runbook

Use this when you have interview transcripts (no video) and want to test scoring, grounding, and report UI.

## Prerequisites

- `.env` on server: `ANALYSIS_PIPELINE=multipass`, `ANALYSIS_GROUNDING_PASS=true`, Azure OpenAI keys set
- Migration `006_multipass_analysis.sql` applied: `php database/migrate.php`

## 0. Create interview shells (if none in UI)

`--list` may show old test rows (e.g. stuck `processing` from a failed video upload). For real UX review, create one row per transcript:

```bash
php scripts/create_interview.php --list-jobs
php scripts/create_interview.php --job-id=<job-uuid> --first-name=Candidate --last-name=One --email=c1@test.local
php scripts/create_interview.php --job-id=<job-uuid> --first-name=Candidate --last-name=Two --email=c2@test.local
```

Use a real job (with rubric + questions) — not only the placeholder `test` job if you want meaningful scores.

## 1. List interviews

On the server (project root):

```bash
php scripts/analyze_transcript.php --list
```

Pick an `interview_id` for each transcript. Match job + candidate to your real interviews.

## 2. Run analysis (one transcript)

```bash
php scripts/analyze_transcript.php --interview-id=<uuid> --file=/path/to/transcript1.txt
```

Options:

| Flag | Purpose |
|------|---------|
| `--skip-refine` | Skip GPT transcript cleanup (faster, use if transcript is already clean) |
| `--reanalyze` | Re-run on transcript already saved in DB (no `--file`) |

CLI prints: report URL, overall score, recommendation, grounding score, safe summary.

Typical run: 2–5 minutes per long interview (refine + 3 AI passes).

## 3. Open report in browser

Use the printed URL, e.g.:

`https://your-app.example.com/jobs/{jobId}/reports/{reportId}`

Log in as a user with access to that job.

## 4. UX checklist

### Overview tab

- [ ] **AI Grounding (v3)** banner — green if grounding ≥ 70, red if &lt; 70
- [ ] Grounding score, evidence strength, hallucination risk
- [ ] **Verified summary** (grounding-safe text)
- [ ] Strengths / weaknesses / recommendation — read for hallucinations vs transcript
- [ ] Red flags and follow-up questions feel grounded

### Scorecard tab

- [ ] Overall + experience / culture / soft skills scores
- [ ] Per-rubric criterion scores with **evidence notes** and **quotes**
- [ ] Criteria marked **insufficient evidence** show `–` with explanation (not scored)

### AI Notes tab

- [ ] Q&A pairs match planned questions and transcript content

### Transcript tab

- [ ] Full text from your file (refined version if refine was on)

### Right panel

- [ ] Video empty or placeholder OK for manual runs
- [ ] Integrity section reasonable (manual = no window-switch events)

## 5. Repeat for 2–3 interviews

Compare:

- Score spread across candidates
- Grounding scores and flagged claims
- Whether evidence quotes are real lines from transcript
- Recommendation (Must / Maybe / No) vs your judgment

## 6. Re-run after prompt tweaks

```bash
php scripts/analyze_transcript.php --interview-id=<uuid> --reanalyze
```

Or upload a revised file with `--file=...` (replaces saved transcript).

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Interview not found | Create interview in UI or use `--list` id |
| Azure API error | `php scripts/test_integrations.php` |
| Empty scorecard | Check job rubric criteria in job settings |
| No grounding banner | Old report or `ANALYSIS_GROUNDING_PASS=false` — re-analyze |

## Notes

- Manual runs set interview status to `completed` and save transcript with `provider=manual`.
- `raw_analysis_json` holds full v3 payload for debugging; UI reads grounding + evidence from it.
