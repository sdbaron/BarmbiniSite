---
name: deployment-safety-check
description: "Use when preparing, reviewing, or documenting Barmbini WordPress deployments, migrations, live updates, Mode A vs Mode B decisions, SQL imports, backups, rollback plans, or remote server rollouts."
argument-hint: "Describe the planned deployment, migration, or update"
user-invocable: true
---

# Deployment Safety Check

## When To Use

- Before deploying local WordPress changes to the remote server.
- When reviewing a migration, import, rollback, or backup procedure.
- When deciding whether a task belongs to Mode A or Mode B.
- When a request mentions SQL imports, `scp`, archive transfer, `wp-content`, uploads, or live data preservation.

## Procedure

1. Classify the task.

- Decide whether it is docs-only, local-only, or changes the live server.
- If it touches the live server, continue with the full check.

2. Choose the deployment mode.

- Mode A: full alignment is allowed only if the live database may be replaced.
- Mode B: use when live users, customer data, orders, uploads, or other live-only records must be preserved.

3. Verify required inputs.

- Confirm the local WordPress source exists.
- Confirm backup and rollback steps are defined.
- Use a full SQL dump only for Mode A.
- Use a code-only archive for Mode B when appropriate.

4. Block unsafe operations.

- Never run a full SQL import in Mode B.
- Never blindly delete live `uploads` in Mode B.
- Never treat undocumented live fixes as a valid source of truth.

5. Prefer the correct documents.

- Use `Barmbini_Aufgabe_Update_von_local_auf_Server.md` for the standard update path.
- Use `Barmbini_Aufgabe_Update_Modus_B_Live_Daten_erhalten.md` when live data must survive.
- Use `Barmbini_Migrationsdurchfuehrung_2026-04-22.md` for validated production facts.

6. Produce a deployment decision record.

- Chosen mode
- Allowed operations
- Blocked operations
- Backup and rollback plan
- Post-deploy validation checklist

## Project-Specific Checks

- Treat Kadence as the validated active theme unless newer verified evidence overrides it.
- Do not confuse themes present in local archives with themes actively used in production.
- Treat the server as security-sensitive because prior compromise and cleanup were documented.

## Expected Output

- Short risk summary
- Mode A or Mode B decision
- Safe sequence of steps
- Validation checklist
- Open questions that must be resolved before deployment