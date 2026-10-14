# AI Prompts — TalentPulse

> **Full prompt text (all system + user templates):** [`PROMPTS_FULL.md`](PROMPTS_FULL.md)  
> **Version:** `v4-speaker-musthave` (`InterviewAnalysisOrchestrator::PROMPT_VERSION`) — see revision notes in repo history / `AzureOpenAIService.php`  
> **Source of truth in code:** `app/Services/AzureOpenAIService.php`  
> **Disable grounding pass:** `ANALYSIS_GROUNDING_PASS=false` in `.env`

## Pipeline

| Step | Model | Purpose |
|------|-------|---------|
| Refine | gpt-4.1-mini | Fix STT errors only |
| Pass 1 | gpt-4.1-mini | Q&A extraction + transcript quotes |
| Pass 2 | gpt-4.1 | Evidence-based scoring |
| Pass 3 | gpt-4.1-mini | Grounding audit (flags unsupported claims) |

## Anti-hallucination design

1. **Transcript-only truth** — JD/resume not in prompts yet; scores only from what was said.
2. **Mandatory quotes** — Q&A pairs require `transcript_quote`; criteria require `evidence_quotes[]`.
3. **Explicit gaps** — `"Not discussed in interview"` instead of invented content.
4. **Null scores** — `insufficient_evidence: true` → no criterion row saved.
5. **Scoring anchors** — 0–3 / 4–5 / 6–7 / 8–9 / 10 definitions in prompt.
6. **Grounding audit** — Third pass caps inflated scores and prepends warning if `grounding_score < 70`.
7. **Temperature 0** — Scoring and extraction runs at `0.0`.

## Context injected

| Field | Source |
|-------|--------|
| `job_title`, `qualifications`, `must_have`, `key_skills` | `jobs` table |
| `criteria_rubric` | `job_rubric_criteria` (name + description) |
| `questions` | `job_interview_questions` |
| `transcript` | Refined ElevenLabs output |
| `qa_pairs` | Pass 1 output → Pass 2 & 3 |

## Output stored

- Report columns: overview fields, scores (evidence-rich JSON in `raw_analysis_json`)
- New JSON keys: `grounding_audit`, `grounding_safe_summary`, `analysis_metadata`, `planned_questions_missed`

## Tuning

Edit prompt builders in `AzureOpenAIService.php`:

- `buildQaExtractionPrompt()`
- `buildScoringPrompt()`
- `buildGroundingAuditPrompt()`
- `groundingRulesBlock()` — shared rules

After changes, bump `PROMPT_VERSION` in `InterviewAnalysisOrchestrator.php`.

## Re-run analysis

Existing reports keep old prompts.

**Manual transcript (no video):**
```bash
php scripts/analyze_transcript.php --list
php scripts/analyze_transcript.php --interview-id=<uuid> --file=path/to/transcript.txt
php scripts/analyze_transcript.php --interview-id=<uuid> --reanalyze
```

Open the printed report URL to review UI/UX.
