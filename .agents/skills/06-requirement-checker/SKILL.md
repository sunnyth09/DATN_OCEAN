---
name: requirement-checker
description: Verify every user requirement individually and ensure prohibited changes were not made.
---

# Requirement Checker

## Goal

Prevent "technically works but does not follow the request."

## Create a checklist

For every requirement:

- [PASS] Requirement implemented and verified
- [FAIL] Requirement not satisfied
- [NOT VERIFIED] Cannot prove it yet

For every prohibition:

- [PASS] Prohibited area untouched
- [FAIL] Prohibited area changed

## Important

Do not infer PASS from intention.

Example:

Requirement:
"Do not modify ProductCard."

Correct verification:
- inspect git diff
- confirm ProductCard is unchanged

Incorrect:
"I did not intentionally modify ProductCard."

## Verify exact scope

Compare:
- planned files
- actual changed files
- requested behavior
- actual behavior

## Final rule

Any unresolved requirement means:
NOT DONE.

Do not report completion.
