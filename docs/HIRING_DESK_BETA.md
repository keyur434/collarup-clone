# Hiring desk (preview)

Isolated decision-support UI for HR. **Does not modify** Leaderboard, Candidates, Job Roles list, or legacy `/jobs/{id}/compare`.

## Enable

1. Set in `.env`: `HIRING_DESK_BETA=true`
2. Or in DB `app_settings`: `hiring_desk_beta` = `1` / `true`

When off: no sidebar link, `/hiring-desk*` returns 404.

## Routes

| URL | Purpose |
|-----|---------|
| `/hiring-desk` | Pick a job role |
| `/hiring-desk/jobs/{jobId}` | Applicant pool, assist rank, filters, link to reports |
| `/hiring-desk/jobs/{jobId}/compare` | 2–8 candidates, heatmap + summary snippets |
| `POST /hiring-desk/jobs/{jobId}/insights/generate` | **One Azure OpenAI call** — comparative pool brief (cached) |

## AI hiring brief

- **When:** HR clicks **Generate brief** on the job desk (not on page load).
- **Input:** Job JD fields + rubric + up to 20 candidates’ latest reports (`raw_analysis_json`, scores, Q&A samples).
- **Output:** Cohort summary, suggested shortlist (proposal only), head-to-head tradeoffs, committee questions.
- **Storage:** `job_hiring_insights` (migration `016`). Stale banner when reports/rubric change (`input_hash`).
- **Does not** re-run STT or per-interview analysis.

Reports open the **existing** report pages (`/jobs/{jobId}/reports/{reportId}`).

## Integration policy

1. HR pilots preview only; legacy flows stay default.
2. Collect feedback (ranking useful? compare heatmap? missing fields?).
3. After approval, optional merge steps (document each):
   - Link from job interviews → desk
   - Replace or augment legacy compare
   - Pool AI pass (roadmap Phase 3)

## Features (preview only)

| Feature | Action |
|---------|--------|
| Weights & minimum bar | Save on job desk → **Assist** column |
| Resume vs JD | **Score resumes vs JD** (writes `match_score_grade`, `cv_summaries`) |
| Pool rank (Phase 3) | **Generate pool rank** (batched listwise AI) |
| HR audit | Decision notes form |
| Committee pack | **Committee pack (HTML)** download |

Migration **017** required (`cv_summaries`, `hiring_desk_decision_notes`, `pool_compare_json`).

## Cost (Azure OpenAI)

Buttons **Generate brief**, **Score resumes vs JD**, and **Generate pool rank** call Azure OpenAI (paid per token). Cached where noted. Full map: `docs/PAID_API_SERVICES.md`.

If resume scoring fails with **Missed model deployment**, set `AZURE_OPENAI_DEPLOYMENT` to the exact deployment name in Azure Portal and run `php scripts/test_integrations.php openai`.
