# ADR-0001: Selective Agents Protocol adoption

- Status: Accepted
- Date: 2026-09-28
- Upstream: [HappyMonkeyAI/AgentsProtocol](https://github.com/HappyMonkeyAI/AgentsProtocol/), inspected at `d520b2b20318511dd4fbd23d84b8408dce27a430`

## Context
ComfyPress is a small WordPress/ComfyUI plugin workspace with existing product records (`README.md`, `PLAN.md`, `project.json`) and active work in the checkout. It had no agent guidance, repository context file, ADR sequence, or canonical project-memory tree. The upstream protocol provides useful grounding, evidence, and memory mechanisms, but also contains extensive swarm/MCP infrastructure that is not established as part of this repository.

## Decision
Adopt a deliberately small, repository-specific subset:
- `AGENTS.md` defines grounding, dirty-tree preservation, scoped edits, and evidence-based verification.
- `CONTEXT.md` records source-anchored architecture and current constraints.
- `docs/adr/` records durable workflow/architecture decisions.
- `.agent/memories/` is the canonical home for concise codebase insights and procedural lessons.
- `PROGRESS.md` records the adoption provenance and verification scope.

Existing product documentation remains authoritative for feature goals and status, subject to source verification. Memory entries must cite repository evidence and be revised when that evidence changes.

## Not adopted
Do not import the upstream repository wholesale, its example history, skill library, prompts, agent coordination model, mandatory worktree/branch conventions, Ratchet/commit policy, or MCP discovery workflow. This repository has no verified project-specific MCP integration; no `MCP.local.md`, credentials, machine paths, or host-specific service inventory are created. User direction and repository-specific constraints override generic upstream wording.

## Consequences
The project gains a small shared grounding and memory structure without adding runtime dependencies or changing plugin behavior. Documentation can drift, so agents must re-check source and Git state. This ADR documents process adoption only; it is not evidence of plugin security, build, or runtime acceptance.

## Verification
Protocol documentation is verified by file readback, ignored/tracked-path checks, and `git diff --check`. Product tests/builds are not claimed by this documentation-only change.