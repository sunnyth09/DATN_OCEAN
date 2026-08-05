---
name: repo-analyzer
description: Analyze repository structure, stack, conventions, entry points, dependencies, scripts, tests, and configuration before implementation.
---

# Repository Analyzer

## Goal

Understand the existing system before touching it.

## Inspect first

Identify as applicable:

- frontend entry point
- backend entry point
- mobile entry point
- routes
- controllers
- services
- models
- stores/state
- composables/hooks
- components
- API clients
- database migrations/schema
- tests
- build scripts
- Docker Compose files
- environment/config files
- lint/type-check/build commands

## Stack detection

Confirm actual versions from repository files instead of assuming.

Examples:
- `composer.json`
- `package.json`
- `pubspec.yaml`
- `docker-compose.yml`
- `vite.config.*`
- Laravel config
- Vue config

## Existing behavior

Before modifying a feature, find:
1. where it starts
2. where data flows
3. where state is stored
4. where API calls occur
5. where persistence occurs
6. what consumes the output

## Important

Do not make changes during analysis unless a harmless diagnostic action is required.

Do not rewrite architecture based on assumptions.

## Output expected internally

- relevant files
- dependency chain
- existing conventions
- test/build commands
- risk areas
- unknowns requiring inspection
