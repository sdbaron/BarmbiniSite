# Project Guidelines

## Project Type

- This workspace documents and prepares the WordPress/WooCommerce project for Sozialkaufhaus Barmbini.
- Treat the site as German-only unless a newer approved document explicitly says otherwise.
- If code is missing from the workspace, work from the documented architecture and operational runbooks instead of guessing.

## Architecture

- WooCommerce is used as a product catalog, not as a checkout or payment shop.
- Prefer project-specific logic in a custom plugin such as `wp-content/plugins/barmbini-core/`.
- Do not add new business logic directly to `themes/kadence/functions.php`.
- Use a child theme only for template, markup, or CSS-heavy overrides.

## Source Of Truth

- Prefer operational documents over older concept documents when they conflict.
- Treat `Barmbini_Technisches_Konzept_v2.5.md` as the current target concept.
- Treat `Barmbini_Migrationsdurchfuehrung_2026-04-22.md`, `Barmbini_Aufgabe_Update_von_local_auf_Server.md`, and `Barmbini_Aufgabe_Update_Modus_B_Live_Daten_erhalten.md` as the primary live-operational references.
- Use `Barmbini_Vorbereitung_Features_und_Bugfixes.md` as the main implementation preparation note.

## Deployment Rules

- Before any live update, decide explicitly whether Mode A or Mode B applies.
- If live users, customer data, orders, or other live-only records must survive, do not perform a full SQL import.
- Avoid manual live-only fixes that are not reflected in the local source and runbooks.

## Privacy And Safety

- Any feature involving accounts, subscriptions, notifications, or other personal data requires a legal and privacy review.
- Update the legal documents when new personal-data processing is introduced.
- Treat the server as security-sensitive because prior compromise and cleanup are documented.

## Documentation Practice

- Keep new runbooks and feature notes explicit, implementation-oriented, and reversible where possible.
- When a task changes the technical process, update the relevant documentation in the same work item.