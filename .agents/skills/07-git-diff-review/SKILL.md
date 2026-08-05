---
name: git-diff-review
description: Review git status and diff to detect unintended changes, scope creep, accidental deletions, generated files, secrets, and unrelated refactors.
---

# Git Diff Review

## Goal

Use Git as the final audit trail.

## Inspect

Use:
- `git status`
- `git diff --stat`
- `git diff`
- appropriate staged diff commands if applicable

## Look for

- unrelated files
- accidental deletions
- accidental renames
- generated files
- lockfile changes
- dependency changes
- environment files
- secrets
- debug code
- console/log spam
- temporary files
- formatting-only noise
- API changes
- database changes
- hidden behavior changes

## Scope rule

Every changed file must have a reason connected to the task.

If a file has no valid reason:
- revert the unrelated change if safe
- otherwise investigate before completion

## Secret safety

Never commit:
- `.env`
- API keys
- tokens
- passwords
- private keys
- credentials

unless the repository explicitly uses safe placeholders/examples.

## Completion

A clean diff is not enough; it must also be the correct diff.
