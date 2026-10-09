# Filtered reports and PDF exports

Reports & Insights and its PDF endpoint remain official-only. Administrator status is not required. Guests, residents, personnel, inactive accounts and temporary-password accounts cannot download PDFs. Other pages retain the existing CSV helper and endpoint.

## Filter contract and scope

`includes/reporting.php` validates all inputs against the existing catalog, workflow and account/location options. The shared database condition is in `database/database.php`; it is used by counts, batched record reads and feedback queries. Dates refer to original submission timestamps in Asia/Manila, with an exclusive next-day bound for an inclusive end date. Presets use local calendar days and Monday-based weeks. Download/pagination URLs freeze resolved preset dates to prevent a midnight change in period.

The store assembles a repeatable-read, read-only snapshot, incrementally computes full-set insights, and retains just 20 preview records (all records when exporting). A reporting projection excludes image payloads, reporter identities, tokens and internal notes. Search covers IDs, titles, categories and types, not reporter names or exact locations. Inactive personnel and locations remain available for historical filtering. Unassigned team/personnel filters include both null and empty assignments; legacy name-only location records can match an existing registered location.

Filtered widgets: matching count, outcome cards, resolution average, reopen counts, category/type/status/priority/key-point/location distributions, chronological monthly submissions, active personnel workload ranking, priority decision matrix and feedback. The existing weekly overview/action plans, configured recurrence-history view and overall personnel-capacity table deliberately retain independent scope, clearly labeled next to their headings. This retains their original operational meaning.

## PDF package and reproducibility

Official release archive: https://github.com/dompdf/dompdf/releases/download/v3.1.6/dompdf-3.1.6.zip

SHA-256: `05df8ee4907325ed2e09a139de9784325f761b43a049297155499270163ac94d`

Bundled versions (also recorded in `vendor/dompdf/vendor/composer/installed.json`):

- dompdf/dompdf 3.1.6
- dompdf/php-font-lib 1.0.2
- dompdf/php-svg-lib 1.0.2
- masterminds/html5 2.10.1
- sabberworm/php-css-parser 8.9.0

This is the upstream packaged release, including production dependencies and their license notices; no root Composer setup was introduced. To update, review upstream release/security notes, download the full packaged release, verify the archive, replace only this dependency directory, update this manifest, and rerun functional, HTTP, browser and rendered-PDF QA before release. Do not use GitHub's source-only archive, which omits dependencies.

## Document and resource policy

The PDF uses A4 landscape, bundled DejaVu Unicode fonts, the existing SVG brand icon, fixed running branding/footer with Page X of Y, applied filters, executive metrics, ranked distributions, a complete repeating-header concern register, and full location/description detail sections. Long descriptions flow outside table cells to avoid Dompdf's non-pageable-row restriction. All database text is escaped; user content never becomes HTML, a resource URL or executable code.

The endpoint validates filters afresh and emits binary headers only after rendering succeeds. It uses no-store caching and no persistent PDF files. It checks estimated memory before collecting export rows and again against the actual template size, returning HTTP 422 with an instruction to narrow the filters when necessary. There is no fixed record-count cutoff, no record omission and no fallback to global data. Capacity follows PHP's configured memory limit (a conservative 512 MB budget applies when PHP memory is unlimited). Font caches are outside the public tree. Generation time and capacity remain subject to the hosting account's runtime limits.

## Verification

`php tests/reporting.php` uses a disposable database, including bulk isolated fixtures, and writes invented-data QA PDFs to ignored `tests/tmp`. `tests/reports-http.php` runs through `tests/http.php`, testing roles, binary headers, invalid filters, empty reports and unchanged CSV dependencies. The browser harness covers category-dependent options, persistence/reset, PDF filter URLs and widths 375/430/768/1024/1440 in both themes. `php tools/verify.php --browser` integrates these checks. PDF QA should additionally render/extract the fixtures with Poppler and visually inspect first, continuation and long-description pages.

## Implementation inventory

Modified for this reporting task:

- `reports.php`: validated dataset, PDF action, filtered cards/rankings/feedback, independent scope labels, matching preview.
- `includes/page.php`: avoid loading full concern/photo payloads for Reports.
- `includes/store.php`: authorized filter options and snapshot-based reporting service.
- `database/database.php`: central shared SQL condition, read-only snapshot, keyset-batched lightweight projection and filtered feedback.
- `assets/css/app.css`: token-based responsive filter and preview layouts.
- `tools/build-release.php`: include PDF endpoint and complete bundled dependencies.
- `tools/health-check.php`: check DOM extension and bundled renderer.
- `tools/verify.php`: run reporting regression suite.
- `tests/http.php`: capture HTTP headers and integrate report endpoint checks.
- `tests/system-upgrade-http.php`: explicitly require successful feedback-report rendering.
- `tests/browser.cjs`: filter interactions and responsive/themed checks.
- `DEPLOYMENT.md`: PHP extensions, cache permissions, package deployment and capacity notes.

Created:

- `includes/reporting.php`: reusable validation, filter labels/URLs and incremental full-dataset summaries.
- `includes/components/report-filters.php`: compact GET form and More Filters disclosure.
- `includes/components/report-preview.php`: paginated, linked matching-concern table and empty state.
- `assets/js/reports.js`: dependent type/keypoint choices and custom-date interactions.
- `includes/report-pdf.php`: escaped branded A4 template and locked-down binary renderer.
- `reports-pdf.php`: authenticated official-only read-only PDF endpoint.
- `vendor/dompdf/`: upstream pinned production renderer, transitive dependencies, fonts and licenses.
- `tests/reporting.php`: isolated filter/data/Unicode/PDF/privacy/capacity tests.
- `tests/reports-http.php`: real HTTP role, headers, invalid-filter and CSV-regression checks.
- `docs/REPORTING.md`: architecture, dependencies, limits and implementation record.

No database migration or production record change was required. `api.php`, `br_export()` and the existing CSV consumers were intentionally preserved. The earlier sidebar promotion is separate from this module upgrade. The pre-existing `config/database.php` worktree change was not edited.
