---
name: implementation
description: Implement the planned change with strict scope control and minimal unrelated modifications.
---

# Implementation Guardrails

## Primary rule

Implement the approved plan. Do not opportunistically redesign the project.

## Before editing

Know:
- allowed files
- forbidden files
- acceptance criteria
- existing conventions
- verification commands

## Minimal change principle

Prefer:
- smallest safe diff
- existing abstractions
- existing dependencies
- existing naming conventions

Avoid:
- unnecessary refactors
- new dependencies
- new architecture
- duplicate utilities
- unrelated formatting
- changing working APIs
- changing database structure without requirement

## Scope violation

If implementation appears to require an out-of-scope change:

1. Inspect why.
2. Determine whether it is genuinely necessary.
3. If the agent can safely continue without it, do not make it.
4. If it is genuinely necessary, mark it as an explicit scope exception internally and verify it carefully.

Never silently expand scope.

## Preserve behavior

Unless requested, preserve:
- existing business rules
- API contracts
- authentication
- authorization
- database behavior
- validation
- error handling
- existing responsive behavior
- existing public interfaces

## No premature completion

After editing, the task is NOT complete.
Move to verification.
