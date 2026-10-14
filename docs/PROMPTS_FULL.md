# Full AI prompts (interview analysis)

> **Prompt version:** `v4-speaker-musthave` (`InterviewAnalysisOrchestrator::PROMPT_VERSION`)  
> **Runtime source of truth:** `app/Services/AzureOpenAIService.php`  
> **Orchestration:** `app/Services/InterviewAnalysisOrchestrator.php`  
> **Context assembly:** `app/Jobs/AnalyzeInterviewJob.php`  
>
> When you change prompts in PHP, update this file to match.  
> Resume parsing uses **no** GPT (`ResumeParserService.php`). STT is **ElevenLabs**, not OpenAI.

## Pipeline overview

| Order | Call | Model (typical) | Temperature | JSON mode |
|-------|------|-----------------|-------------|-----------|
| 0 | `refineTranscript()` | `AZURE_OPENAI_REFINE_DEPLOYMENT` (mini) | 0.0 | no |
| 1 | `extractQaPairs()` | `AZURE_OPENAI_EXTRACTION_DEPLOYMENT` | 0.0 | yes |
| 2 | `analyzeScoring()` | `AZURE_OPENAI_DEPLOYMENT` (gpt-4.1) | 0.0 | yes |
| 3 | `auditGrounding()` | extraction deployment | 0.0 | yes (if `ANALYSIS_GROUNDING_PASS` enabled) |

Legacy single-pass pipeline removed (Sep 2026). Use `ANALYSIS_GROUNDING_PASS=false` to skip Pass 3 only.

---

## Default system message (`chat()` fallback)

Used when no per-pass system message is passed (legacy path only).

```
You are an expert HR interview analyst. Return valid JSON only when requested. Never invent facts not present in the transcript.
```

---

## 0 — Transcript refine

**System (`systemMessageRefine`):**

```
You correct speech-to-text errors only. Never translate, summarize, or add content. Output plain transcript text only.
```

**User (`refineTranscript`):**

```
You fix speech-to-text output from a job interview for {{job_title}}.

The raw transcript may mix Hindi, Gujarati, and English (Hinglish). Key skills/context: {{key_skills}}

STRICT rules:
- Fix obvious misheard words and romanization only
- Keep the same languages — do NOT translate Hindi/Gujarati to English
- Do NOT add, remove, or summarize lines — same number of speaker turns as raw
- Do NOT add facts not clearly present in the raw text
- Do NOT write phrases like "removed for clarity" or markdown labels like **Agent:**
- Preserve [Interviewer]: and [Candidate]: prefixes if present

Return ONLY the corrected transcript. No preamble.

Raw transcript:
{{raw_transcript}}
```

**Runtime variables:** `job_title`, `key_skills` from job; `raw_transcript` = ElevenLabs text.

---

## Shared block — grounding rules

Included in **Pass 1** and **Pass 2** user prompts (`groundingRulesBlock()`).

```
GROUNDING RULES (mandatory):
1. The transcript is the ONLY source of truth. Job description fields are for context — not evidence the candidate demonstrated those skills.
2. Do NOT use resume knowledge, stereotypes, or assumptions about the role.
3. Every factual claim must be supportable by a direct quote or clear paraphrase from the transcript.
4. If a topic was not discussed, write exactly: "Not discussed in interview" — never guess.
5. Hinglish answers are valid — do not penalize mixed Hindi/Gujarati/English.
6. When audio is unclear, say "Unclear from transcript" instead of inventing content.
7. Prefer lower scores over confident guesses when evidence is weak.
8. Planned questions listed below may NOT have been asked — only credit what appears in the transcript.

SCORING ANCHORS (use these consistently):
- 0-3: No evidence or clear gap vs requirement
- 4-5: Limited or vague evidence
- 6-7: Adequate evidence with some gaps
- 8-9: Strong, specific evidence with examples from the interview
- 10: Exceptional, repeated strong evidence (rare — use sparingly)
```

---

## Pass 1 — Q&A extraction

**System (`systemMessageExtraction`):**

```
You extract interview Q&A from transcripts. No scoring or judgment. Every answer must be traceable to the transcript. Return valid JSON only.
```

**User (`buildQaExtractionPrompt`):**

```
Extract question-and-answer pairs from this job interview transcript.

{{grounding_rules_block}}

EXTRACTION RULES (judgment-free):
- Do NOT score, rank, or evaluate the candidate
- Do NOT summarize quality — capture what was said
- Preserve Hinglish / mixed language in answer_text when spoken that way
- question: use the interviewer's words from the transcript when possible
- answer_text: candidate's words; paraphrase only if verbatim is unreadable
- transcript_quote: REQUIRED — shortest verbatim snippet from transcript supporting the answer (1-2 lines)
- confidence: high | medium | low (low if audio unclear or overlapping speakers)
- If no clear answer: answer_text="", confidence="low", note in extraction_notes
- Do NOT invent questions that are not in the transcript

Return JSON:
{
  "qa_pairs": [
    {
      "order": 1,
      "question": "interviewer question",
      "answer_text": "candidate response",
      "transcript_quote": "verbatim snippet from transcript",
      "confidence": "high|medium|low",
      "asked_by": "interviewer",
      "answered_by": "candidate"
    }
  ],
  "planned_questions_asked": ["list planned questions that were actually asked"],
  "planned_questions_not_asked": ["planned questions with no evidence in transcript"],
  "extraction_notes": "unclear audio, overlaps, or gaps"
}

Job Title: {{job_title}}

Planned interview questions (mark which were/were not asked):
{{questions}}

Transcript:
{{transcript}}
```

**Runtime variables:** `job_title`, `questions` (bulleted list from `job_interview_questions`), `transcript` (refined).

---

## Pass 2 — Scoring

**System (`systemMessageScoring`):**

```
You score job interviews using ONLY explicit transcript evidence. If evidence is missing, say "Not discussed in interview" or use null scores. Never infer resume facts or assume skills not demonstrated. Return valid JSON only.
```

**User (`buildScoringPrompt`):**

```
Score this job interview using the rubric. Pre-extracted Q&A pairs are provided — do NOT re-extract Q&A.

{{grounding_rules_block}}

OUTPUT RULES:
- Every overview field must cite transcript-backed facts only
- criterion_scores: include evidence_quotes[] (1-3 short quotes per criterion)
- If insufficient_evidence is true, set score to null and explain in evidence_notes
- integrity: only flag cheating if specific patterns appear in transcript (e.g. reading scripted answers, third-party voice) — default low
- information_completeness_score: % of planned questions actually covered in transcript
- topic_adherence_score: how well interview stayed on role-related topics (from transcript only)
- recommendation: Must | Maybe | No — with one-sentence reason tied to evidence

Return JSON:
{
  "overall_score": 0-10 or null,
  "experience_score": 0-10 or null,
  "culture_score": 0-10 or null,
  "soft_skills_score": 0-10 or null,
  "criterion_scores": [
    {
      "name": "criterion name",
      "score": 0-10 or null,
      "insufficient_evidence": false,
      "evidence_quotes": ["quote from transcript"],
      "evidence_notes": "what supports this score, or why evidence is missing"
    }
  ],
  "overview": {
    "strengths": "bullet facts from transcript only, or Not discussed in interview",
    "weaknesses": "bullet facts from transcript only, or Not discussed in interview",
    "red_flags": "only concrete concerns from transcript, or None identified",
    "team_fit": "evidence-based or Not discussed in interview",
    "cheating_detection": "evidence-based or No signs detected in transcript",
    "qualification_match": "based on what candidate SAID in interview vs JD — not resume",
    "logistics": "salary, location, notice period if discussed, else Not discussed in interview",
    "follow_up": "suggested follow-up questions for areas not covered",
    "recommendation": "Must | Maybe | No — reason"
  },
  "conversation_phases": [{"phase": "opening|technical|behavioral|closing", "summary": "brief factual summary"}],
  "key_questions_captured": ["themes actually discussed"],
  "planned_questions_missed": ["planned but not asked or not answered"],
  "topic_adherence_score": 0-100,
  "information_completeness_score": 0-100,
  "analysis_metadata": {
    "transcript_quality": "good|fair|poor",
    "evidence_strength": "strong|moderate|weak",
    "hallucination_risk": "low|medium|high"
  },
  "integrity": {
    "cheating_likelihood": "low|medium|high",
    "ai_answer_patterns": "not_detected|detected|na",
    "integrity_notes": "specific transcript evidence or none"
  }
}

Job Title: {{job_title}}
Key Skills: {{key_skills}}
Qualifications: {{qualifications}}
Must Have: {{must_have}}

Scoring rubric (score ONLY against evidence in transcript):
{{criteria_rubric}}

Interview Questions (planned):
{{questions}}

Pre-extracted Q&A pairs:
{{qa_pairs_json}}

Full Transcript:
{{transcript}}
```

**Runtime variables:** all Pass 1 fields plus `key_skills`, `qualifications`, `must_have`, `criteria_rubric` (from `job_rubric_criteria`), `qa_pairs` JSON from Pass 1.

---

## Pass 3 — Grounding audit

**System (`systemMessageGrounding`):**

```
You audit interview analysis for unsupported claims. Be strict: flag anything not directly supported by transcript quotes. Return valid JSON only.
```

**User (`buildGroundingAuditPrompt`):**

```
Audit the interview SCORING OUTPUT against the transcript. Your job is to catch hallucinations and unsupported claims.

RULES:
- Compare every strength, weakness, score, and integrity claim to the transcript
- Mark claims as supported only if a direct quote exists
- Suggest lowering scores that lack evidence
- Do NOT add new candidate facts
- safe_summary: 2-3 sentences using ONLY verified transcript facts

Return JSON:
{
  "grounding_score": 0-100,
  "unsupported_claims": [
    {
      "location": "overview.strengths | criterion_scores[0] | etc",
      "claim": "the questionable claim",
      "verdict": "unsupported|partial|supported",
      "transcript_quote": "quote if supported, else empty"
    }
  ],
  "criterion_flags": [
    {
      "name": "criterion name",
      "issue": "why score may be too high",
      "suggested_max_score": 0-10
    }
  ],
  "missing_planned_questions": ["planned questions not found in transcript"],
  "transcript_quality_issues": ["gaps, unclear audio, etc"],
  "safe_summary": "conservative summary for HR",
  "recommendation_adjustment": "no_change|strengthen_warning|downgrade",
  "recommendation_note": "final recommendation text for HR — conservative, evidence-based"
}

Job Title: {{job_title}}

Scoring output to audit:
{{scoring_result_json}}

Transcript:
{{transcript}}
```

**Runtime variables:** `job_title`, full Pass 2 JSON as `scoring_result`, `transcript`.

---

## Legacy — single-pass analysis

**Used when:** `ANALYSIS_PIPELINE=legacy` → `buildLegacyAnalysisPrompt()`.

**User:**

```
Analyze this job interview transcript and return JSON with this exact structure:
{
  "overall_score": 0-10,
  "experience_score": 0-10,
  "culture_score": 0-10,
  "soft_skills_score": 0-10,
  "criterion_scores": [{"name": "criterion name", "score": 0-10}],
  "overview": {
    "strengths": "text",
    "weaknesses": "text",
    "red_flags": "text",
    "team_fit": "text",
    "cheating_detection": "text",
    "qualification_match": "text",
    "logistics": "text",
    "follow_up": "text",
    "recommendation": "text"
  },
  "qa_notes": [{"question": "question asked", "answer_summary": "summary of answer"}],
  "conversation_phases": [{"phase": "opening|technical|behavioral|closing", "summary": "brief summary"}],
  "key_questions_captured": ["important question themes covered"],
  "topic_adherence_score": 0-100,
  "information_completeness_score": 0-100,
  "integrity": {
    "cheating_likelihood": "low|medium|high",
    "ai_answer_patterns": "not_detected|detected|na"
  }
}

Job Title: {{job_title}}
Key Skills: {{key_skills}}
Qualifications: {{qualifications}}
Must Have: {{must_have}}
Scoring Criteria: {{criteria_comma_separated}}

Interview Questions:
{{questions}}

Transcript:
{{transcript}}
```

---

## Context injected at runtime (`AnalyzeInterviewJob`)

| Variable | DB / source |
|----------|-------------|
| `job_title` | `jobs.title` |
| `key_skills` | `jobs.key_skills` (JSON → comma string) |
| `qualifications` | `jobs.qualifications` |
| `must_have` | `jobs.must_have_criteria` |
| `questions` | `job_interview_questions.question_text` (bulleted) |
| `criteria` / `criteria_rubric` | `job_rubric_criteria` (name + description); defaults if empty |
| `transcript` | ElevenLabs STT → optional refine pass |
| `qa_pairs` | Pass 1 output (Pass 2 & 3) |
| `scoring_result` | Pass 2 output (Pass 3) |

---

## Environment toggles

| Variable | Effect |
|----------|--------|
| `ANALYSIS_PIPELINE` | `legacy` vs multi-pass (default orchestrator) |
| `ANALYSIS_GROUNDING_PASS` | `false` skips Pass 3 |
| `AZURE_OPENAI_REFINE_ENABLED` | `false` skips transcript refine |
| Azure OpenAI unconfigured | `mockAnalysis()` fake JSON (no real prompts sent) |

---

## After editing

1. Change `app/Services/AzureOpenAIService.php`.
2. Bump `InterviewAnalysisOrchestrator::PROMPT_VERSION`.
3. Sync this file if you maintain it manually.
4. Re-analyze interviews to refresh reports (`scripts/analyze_transcript.php --reanalyze` or queue `AnalyzeInterview`).
