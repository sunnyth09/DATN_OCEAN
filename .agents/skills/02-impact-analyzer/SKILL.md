---
name: impact-analyzer
description: Trace dependencies and downstream effects before modifying code to prevent fixing one area while breaking another.
---

# Impact Analyzer

## Goal

Prevent "fix the head, break the tail".

## Before changing a file

Trace:

INPUT
→ COMPONENT / CONTROLLER
→ STATE / SERVICE
→ API
→ BACKEND
→ DATABASE
→ OUTPUT
→ DOWNSTREAM CONSUMERS

Adapt the chain to the actual project.

## Search for

- imports
- exports
- component usage
- route references
- API endpoints
- request/response fields
- shared types/interfaces
- store actions
- database relationships
- event listeners
- middleware
- background jobs
- environment variables
- Docker service dependencies

## Risk classification

### LOW
Local styling or isolated presentation change.

### MEDIUM
Shared component, store, composable, service, API client.

### HIGH
Authentication, checkout, payment, order flow, database schema, migrations, deployment, Docker networking, shared API contract.

High-risk changes require stronger verification.

## Regression candidates

Before implementation, identify what could break:
- current feature
- direct consumers
- sibling flows
- mobile/desktop behavior
- backend/frontend contract
- existing tests
- production configuration

## Rule

Never assume "the edited file works" means the feature works.

Verify the complete affected path.
