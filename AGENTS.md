# ComfyPress agent guidance

## Grounding
- Start with `README.md`, `CONTEXT.md`, `PLAN.md`, and `git status --short`; inspect the relevant source and current diff before editing.
- Treat the repository's product docs and the user's current direction as authoritative. This file adds workflow guardrails; it does not replace project-specific plans.
- Canonical repository memory is `.agent/memories/`. Read relevant entries before non-trivial work and update them only when source-backed knowledge changes.

## Change discipline
- Preserve pre-existing modified and untracked files. Do not reset, clean, stash, or include unrelated files in edits or commits.
- Keep changes scoped; map callers and affected REST/UI boundaries before changing them. Do not copy upstream protocol examples as ComfyPress facts.
- Do not read or expose credentials or secret files. Keep machine-specific endpoints and credentials out of committed docs.
- No commit, push, release, or deployment without explicit user approval.

## Verification
- Discover the applicable project test/build/runtime commands before relying on them. Report exactly what ran and distinguish documentation checks from plugin/runtime acceptance.
- For code changes, run relevant deterministic checks; for REST or Gutenberg behavior changes, verify the affected contract and live behavior when a suitable WordPress/ComfyUI environment is available.
- Before finishing, review `git diff --check`, read back changed files, and confirm unrelated dirty paths remain untouched.

## Protocol provenance
This repository selectively adopts the documentation and memory structure of [HappyMonkeyAI/AgentsProtocol](https://github.com/HappyMonkeyAI/AgentsProtocol/) at commit `d520b2b20318511dd4fbd23d84b8408dce27a430`. See `docs/adr/0001-agents-protocol-adoption.md` for the local compatibility boundary.