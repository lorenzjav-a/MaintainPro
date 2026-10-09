# MaintainPro admin UI refinement — October 10, 2026

## Audit findings

- Weekly Top Concerns rendered each concern as one uninterrupted vertical flow. Keypoint disclosures used the full panel width and `.weekly-overview-grid-active` correctly moved the weekly plans below, but the concern itself did not use the available desktop width.
- The action-plan editor placed context and every field in one wide column. Assignment controls were grouped only by a generic Bootstrap row, while outcome notes were separated from the other descriptive fields.
- The active interface already used the shared Segoe UI typography tokens consistently. Bootstrap is overridden through `--bs-body-font-family`; controls, buttons, panels, public pages, authentication, SweetAlert, messaging, and dark mode inherit the same family. PDF output intentionally remains on DejaVu Sans for renderer compatibility.
- Shared page, section, panel, label, body, and helper sizes already formed a consistent 29/16/14/12px hierarchy. A system-wide font-size rewrite was therefore neither necessary nor safe.

## Implementation

- Weekly concerns now use a compact concern header with report count, priority, and recurrence signals.
- Reported areas, keypoint chips, general suggestions, and the related-concerns link form the summary column. Official keypoint actions receive the wider working column.
- Keypoint disclosures use a responsive two-column card grid on wide screens and retain native `details`/`summary` keyboard behavior. Long text wraps, zero-action keypoints retain their message, and concerns without keypoint actions receive an explicit empty state.
- Weekly action plans remain below active weekly concerns. This gives the primary review workflow enough width without compressing either section.
- Create and edit action-plan views now share a compact context panel and a two-column editor: action information on the left; assignment and scheduling on the right. Outcome notes remain with the descriptive content.
- All form names, hidden revision/idempotency inputs, action names, routes, permissions, optimistic locking, and database structures remain unchanged.

## Responsive behavior

- Above 1000px, concern summaries and official actions use an asymmetric two-column layout; keypoint cards use two columns.
- At 1000px and below, the concern summary and action workspace stack.
- At 650px and below, keypoint cards, context data, and form actions become single-column with full-width primary actions.
- The action-plan editor changes from two columns to one at 900px so controls remain comfortably wide on tablets.

## Verification

- `tools/verify.php`: 134 PHP syntax checks and all functional suites passed, including 1,135 HTTP/page/security checks.
- Targeted planning/reporting suites: 19 personnel action-plan, 198 keypoint solution/weekly selection, and 36 reporting/PDF checks passed.
- `tests/browser.php`: 610 browser checks passed. The isolated sweep covers 375, 390, 430, 768, 1024, 1280, 1440, and 1920px, generates screenshots and computed typography records under `tests/tmp`, and includes representative light/dark coverage.
- No production records, Hostinger settings, secrets, or database schemas were changed.

## Deployment

Deploy `action-plans.php`, `includes/components/weekly-concerns.php`, and `assets/css/app.css` together. The stylesheet already uses a `filemtime` query string, so the changed CSS receives a new cache-busting URL after upload. No migration or configuration change is required.
