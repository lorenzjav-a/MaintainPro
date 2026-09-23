# MaintainPro project conventions

## Required visual consistency

The user explicitly requires every future feature, fix and page to match the existing application's fonts and styling. Treat visual consistency as part of completing the task, not as an optional follow-up.

- The active stylesheet is `assets/css/app.css`. The root `styles.css` is a legacy file and is not used by the application.
- Reuse the shared PHP layouts and existing Bootstrap/SweetAlert components. Keep public, authentication and staff pages visually consistent.
- Use the shared CSS tokens: `--font-ui` (Segoe UI, Arial, sans-serif), `--font-size-body` (14px), `--font-size-small` (12px), `--font-size-section` (16px), and `--font-size-page` (29px with existing mobile overrides).
- Use `.form-label` for labels and numbered form-section headings, `.form-text` for helper text, `.section-title` for standalone section headings, and `.panel-title` inside panel headers. Preserve semantic heading elements and the existing hierarchy; do not make every element the same size.
- Reuse the green/navy palette, `--text-secondary`, `--muted`, `--surface-soft`, shared borders/radii, `.panel`, `.btn`, and form-control styles. Avoid new font families, arbitrary font sizes/colors, inline visual styles, or Bootstrap heading-size utilities that conflict with the shared classes.
- Use monospace only for code-like references, tracking codes and credentials, through `--font-mono`.
- Extend or adjust the relevant shared rule instead of stacking page-specific overrides. Keep new elements consistent in normal, focus, hover, disabled, error and mobile states.
- Load `assets/css/app.css` after Bootstrap, with the existing file-version query to refresh cached styles. Preserve local assets and the PHP/HTML/CSS/Bootstrap/SweetAlert stack.
- Check comparable components on affected public, authentication and staff pages at desktop and mobile widths. Use the existing isolated browser/HTTP checks when appropriate; never create test accounts or test concerns in the live database. Scale verification to the change and preserve existing data.
