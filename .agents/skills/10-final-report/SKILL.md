---
name: final-report
description: Produce a factual completion report only after the completion gate passes.
---

# Final Report

## Report only verified facts

Use this structure:

### Status
`COMPLETED` only if the completion gate passed.

### Changed
- file/path — what changed

### Verification
- command — PASS/FAIL
- command — PASS/FAIL

### Requirements
- requirement — PASS
- requirement — PASS

### Scope
- files changed: ...
- unrelated changes: none / explain

### Notes
Only include real limitations or follow-up work.

## Forbidden

Do not:
- claim tests passed if not run
- claim build passed if not run
- call partial work complete
- hide known failures
- describe intended behavior as actual behavior

If incomplete, use:
`INCOMPLETE — <exact blocker>`
