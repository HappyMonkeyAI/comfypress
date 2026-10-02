# ComfyPress repository memory

This is the canonical, tracked memory root for repository-specific agent context, selectively following HappyMonkeyAI/AgentsProtocol (see `docs/adr/0001-agents-protocol-adoption.md`).

- `codebase_insights/`: concise source-grounded module/boundary notes.
- `architectural_decisions/`: optional supporting notes; durable decisions belong in `docs/adr/`.
- `patterns_and_lessons.md`: reusable implementation/verification observations, not a task log.
- `history/`: use only for durable handoffs or plans that the user explicitly wants retained; do not copy session transcripts or generated artifacts here.

Keep entries small, cite source paths/tests, and mark uncertainty. Update or remove stale claims after checking current code. Do not store secrets, credentials, raw machine endpoints, or temporary task progress in memory.