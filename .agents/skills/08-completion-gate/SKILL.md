---
name: completion-gate
description: Final hard gate that prevents an agent from claiming completion before implementation, verification, regression checks, requirement checks, and diff review pass.
---

# Completion Gate

## This is a HARD GATE

The agent MUST NOT report the task as completed until every applicable item is PASS.

## Gate

### Implementation
- [ ] All requested functionality implemented.
- [ ] No known TODO remains for the requested task.
- [ ] No accidental partial implementation.

### Scope
- [ ] Changed files are justified.
- [ ] Forbidden files are unchanged.
- [ ] No unrelated refactor.

### Verification
- [ ] Applicable build passes.
- [ ] Applicable tests pass.
- [ ] Applicable lint/type-check passes.
- [ ] Runtime/integration checks pass where relevant.

### Regression
- [ ] Direct flow verified.
- [ ] Downstream dependencies checked.
- [ ] Relevant adjacent flows checked.
- [ ] Cross-layer contracts checked where relevant.

### Requirements
- [ ] Every requirement explicitly verified.
- [ ] Every prohibition explicitly checked.
- [ ] No requirement is merely assumed.

### Git
- [ ] `git status` reviewed.
- [ ] `git diff` reviewed.
- [ ] No secrets or accidental files.
- [ ] No unexplained changes.

## Failure policy

If ANY required item is not PASS:

DO NOT SAY:
- "Done"
- "Completed"
- "Finished"
- "Everything is fixed"

Instead:
1. Continue working if the issue is fixable.
2. If blocked, state the exact blocker.
3. Mark the task as incomplete.

## Verification loop

IMPLEMENT
→ TEST
→ FAIL?
→ FIX
→ TEST AGAIN
→ REGRESSION
→ REQUIREMENTS
→ DIFF
→ GATE

## Final report requirements

Only after PASS:
- summarize changes
- list verification commands
- state results
- mention any explicitly accepted limitations

Do not claim checks that were not actually executed.
