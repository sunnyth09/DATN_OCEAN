---
name: test-and-verify
description: Verify implementation using the project's real build, test, lint, type-check, and runtime checks.
---

# Test and Verify

## Goal

Never trust code merely because it looks correct.

## Determine applicable checks

### Laravel
Use available project commands such as:
- `php artisan test`
- `php artisan test --filter=...`
- `php artisan route:list`
- relevant static analysis/lint commands

### Vue / Node
Use available scripts such as:
- `npm run build`
- `npm run test`
- `npm run lint`
- `npm run type-check`

Do not invent scripts that do not exist.

### Flutter
Use available commands such as:
- `flutter analyze`
- `flutter test`
- appropriate build command

### Docker
Use applicable checks such as:
- `docker compose config`
- `docker compose build`
- `docker compose up -d`
- service health checks

## Failure loop

If a verification step fails:

FAIL
→ inspect actual error
→ identify root cause
→ make targeted fix
→ run the failed check again
→ run related checks again

Repeat until PASS or until a genuine blocker is reached.

## Never

- hide test failures
- downgrade or remove tests merely to get green
- report "should work"
- report completion without running applicable checks

## Evidence

Keep track of:
- command
- result
- relevant failure
- fix
- rerun result

Only verified claims may be presented as verified.
