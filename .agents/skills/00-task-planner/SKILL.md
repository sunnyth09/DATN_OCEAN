---
name: task-planner
description: Convert a coding request into explicit requirements, constraints, scope, acceptance criteria, and verification steps before editing code.
---

# Task Planner

## Goal

Turn the user's request into an executable plan before changing code.

## Mandatory behavior

Before implementation:

1. Restate the actual goal internally.
2. Extract explicit requirements.
3. Extract explicit prohibitions.
4. Identify files/modules likely in scope.
5. Define acceptance criteria.
6. Define verification commands.
7. Define what must remain unchanged.

## Scope rules

Create these lists:

### MUST CHANGE
Only files or behavior necessary to satisfy the task.

### MUST NOT CHANGE
Examples:
- unrelated components
- existing API contracts
- database schema
- business logic
- authentication
- shared components
- deployment configuration

unless explicitly required.

### ACCEPTANCE CRITERIA
Each user requirement must become a checkable statement.

Bad:
"Improve the UI."

Good:
"Product list uses the requested layout, existing ProductCard remains unchanged, and mobile layout still renders correctly."

## Plan quality

A plan must be:
- small enough to execute safely
- ordered by dependency
- testable
- reversible where possible

Do not start broad refactoring merely because the current code is imperfect.

## Stop condition

If requirements conflict or the requested behavior cannot be implemented safely from available evidence, inspect the repository first. Do not invent architecture.
