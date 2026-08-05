---
name: regression-check
description: Check direct and indirect functionality after changes, especially downstream consumers and shared modules.
---

# Regression Check

## Goal

Ensure the fix did not break the rest of the affected system.

## Check levels

### Level 1 — Direct
Does the requested feature work?

### Level 2 — Dependencies
Do direct callers/consumers still work?

### Level 3 — Adjacent flows
Do related workflows still work?

### Level 4 — Cross-layer
Does the frontend/backend/mobile/database contract still match?

### Level 5 — Build/runtime
Does the application still build and start correctly?

## High-risk flows

For changes touching these, verify the full flow:
- login/authentication
- cart
- checkout
- order
- payment
- shipping
- product variants
- file/image storage
- API authentication
- database migrations
- Docker networking
- deployment

## Rule

A passing unit test does not automatically prove an end-to-end flow is safe.

Use the narrowest meaningful integration/E2E verification available.
