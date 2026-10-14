# Multi-round hiring cycle (HR workflow)

## Mental model

| Layer | What it stores |
|--------|----------------|
| **Application** | `current_stage_id`, `decision` (`none` / `advance` / `hold` / `reject` / `offer`), `status` |
| **Interview** | One row **per pipeline stage** for that application (Round 1, Round 2, Offer call, etc.) |
| **Interview report** | One (or more) reports per **completed** interview — analysis is per round |

Advancing a candidate does **not** reuse Round 1’s interview row. A **new** `interviews` row is created for the next stage.

## Where to act after Round 1

1. **Report page** — `Advance` moves the application to the next stage and redirects to **Job → Interviews** with the schedule drawer open for the **current** round.
2. **Job → Interviews** (`/jobs/{id}/interviews`) — each round is its own row. The **Current** badge is the stage you must schedule:
   - **Link Teams meeting** / **Schedule** — set `meeting_url`, date, time
   - After the call — **Sync recording** → analysis → **View Report**
3. **Report page again** — use the **Interview rounds** strip to jump between round reports or **Schedule →** for the pending round.
4. **Offer** — **Offer** on the report (or decision dropdown on interviews) when the job pipeline includes an **Offer** stage.

## Pipeline board

**Pipeline** (`/pipeline`) shows candidates by **current** stage only. Open **Job → Interviews** to work on scheduling and recordings for that stage.

## Hiring desk / leaderboard

Those views emphasize **latest scores** for comparison. Per-round history lives on the **report rounds strip** and **Job → Interviews** list (filter by stage).

## Common pitfalls

- Editing pipeline stage on an old report used to move Round 1’s interview to Round 2 — **fixed**: stage changes create/use the correct interview row via `PipelineStageService::setApplicationStage`.
- After advance, look for the row with **Current**, not the completed Round 1 row.
