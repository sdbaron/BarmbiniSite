---
name: feature-implementation-plan
description: "Use when turning Barmbini feature requests into a concrete WordPress implementation plan, especially customer accounts, subscriptions, notifications, WooCommerce account endpoints, plugin architecture, usermeta storage, email flows, support views, and legal follow-up work."
argument-hint: "Describe the feature or paste the feature note to plan"
user-invocable: true
---

# Feature Implementation Plan

## When To Use

- When a feature note must become a technical implementation plan.
- When deciding whether new work belongs in a plugin, child theme, or documentation.
- When reviewing account, subscription, notification, or WooCommerce catalog features.
- When a task needs a structured breakdown of data model, hooks, admin surfaces, tests, and legal impact.

## Procedure

1. Restate the feature scope.

- Summarize the requested behavior.
- List explicit non-goals and constraints from the source document.

2. Map the feature to project defaults.

- German-only site
- WooCommerce catalog only
- Prefer a custom plugin for business logic
- Use a child theme only for template or layout overrides

3. Propose the target architecture.

- Entry points such as plugin bootstrap, account endpoint, admin views, background jobs, or email templates
- Required WordPress or WooCommerce hooks
- Required persistence strategy

4. Define the data model.

- Use `usermeta` for simple per-account settings.
- Use a custom table for notification logs, audit trails, queues, or deduplication records.

5. Define behavior and UI surfaces.

- Customer-facing account UI
- Admin or support visibility
- Trigger rules
- Email content and unsubscribe flow

6. Check legal and privacy impact.

- Consent capture
- Unsubscribe mechanics
- Datenschutz updates
- Data retention and auditability

7. Define testing and rollout.

- Acceptance tests
- Failure and duplicate-protection tests
- Deployment and migration impact

## Project Defaults

- New business logic should move toward a project plugin, not deeper into the Kadence vendor theme.
- For subscriptions and notifications, start from WooCommerce My Account integration, `usermeta`, and a notification log table.
- Avoid external services such as Brevo unless the task explicitly enters a later phase that requires them.

## Recommended Sources

- `Barmbini_Aufgabe_Kundenkonto_Abonnements_und_Benachrichtigungen.md`
- `Barmbini_Technisches_Konzept_v2.5.md`
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md`
- `Barmbini_Rechtliche_Seiten.md`

## Expected Output

- Scope summary
- Target architecture
- Data model
- Hook and trigger map
- UI surfaces
- Privacy and legal changes
- Risks and open questions
- Test cases and rollout notes