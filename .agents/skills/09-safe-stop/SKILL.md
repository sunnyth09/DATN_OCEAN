---
name: safe-stop
description: Prevent destructive or ambiguous actions when the agent lacks sufficient evidence or encounters high-risk changes.
---

# Safe Stop

## Stop instead of guessing when

- requirements conflict
- an API contract is unclear
- a database migration could be destructive
- production data could be affected
- authentication/security behavior is uncertain
- required credentials are missing
- a test environment is unavailable
- the agent cannot determine the correct existing behavior
- an out-of-scope change appears unavoidable

## Safe behavior

Do not:
- invent data
- invent endpoints
- invent environment values
- delete production data
- reset databases blindly
- disable security checks to make tests pass
- remove failing tests
- bypass validation
- force push or destroy history without explicit authorization

## When blocked

Record:
- what was attempted
- what failed
- evidence
- what remains incomplete
- the smallest next action needed

A blocked task is better than a silently broken task.
