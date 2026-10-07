# MaintainPro UI refinement — October 5, 2026

## Architecture and role inventory

Workspace routes call `br_page()` before rendering the shared header, sidebar, topbar, mobile dock and footer. Public routes use `br_public_header()` and `br_public_footer()`. Authentication uses the same CSS tokens with its own two-column layout. Bootstrap supplies base controls; SweetAlert supplies short confirmation dialogs. The active stylesheet is `assets/css/app.css`.

The store and domain enforce concern visibility, immutable submissions, workflow transitions, evidence requirements, assignment access and versions. Database queries remain in `database/database.php`. UI forms preserve their action names, CSRF handling and record versions. Test fixtures use disposable databases and loopback SMTP.

| Page / area | Users and purpose | Primary action | Findings and refinement |
| --- | --- | --- | --- |
| Landing | Guests; service entry | Report concern | Existing ghost navigation retained; reduce hero decoration and normalize category card radius/spacing |
| Report concern | Guests and every account role | Submit concern | Three existing sections retained; normalize nested labels, helper spacing, upload controls and submit separation |
| Tracking | Guests with private codes | View status / reply | Remove duplicated conversation markup and IDs; preserve read-only result, follow-up and private message access |
| Community progress | Public aggregate data | Read statistics | Shared panel, typography and spacing refinements |
| User guide | Every role | Find role instructions | Shared section spacing and comfortable jump links |
| Login / registration / setup / recovery | Public and temporary-password accounts | Authenticate / verify | Consolidate typography, fields and focus; retain OTP and password semantics |
| Dashboard | Resident, personnel, official | Follow own reports / continue assignments / assess | Put actionable banner before statistics; balance metric grids and secondary instructions |
| Concerns / history | Own reporters, assigned personnel, officials | Open record / filter | Preserve GET filters and card layout; improve metadata contrast, filters, reset, assignment context and touch targets |
| Concern detail | Authorized reporters and staff | Valid next workflow action | Retain original-record distinction and section anchors; use a semantic stepper and explain exceptions without implying false progress |
| Evidence / activity | Authorized concern viewers | Review evidence and history | Shared spacing, readable notes and restrained borders |
| Staff messages | Officials and personnel | Open/start/send | Place inbox first in DOM; remove duplicated contact query; reduce creation-form clutter while preserving fields and IDs |
| Floating chat | Authenticated accounts; guests via tracking | Find conversation / send | Retain recent redesign; normalize elevation, focus and modal stacking |
| Notifications | All accounts | Read update | Compact readable rows, clearer empty state and shared actions |
| Reports | Officials | Compare outcomes / export | Retain recent report grouping and bars; inherit shared component normalization |
| Action plans | Officials | Create / assign / update | Fix weekly concerns backlink; shared status badges and mobile stacked record tables |
| My action plans | Assigned personnel | Start / update / complete | Shared badges, mobile records and form separation; no invented progress percentage |
| Administration | System administrators | Open management function | Existing grouped tools retained; shared panel spacing |
| Accounts / create / edit | System administrators | Manage access | Shared mobile record tables, fields, badges, focus and one-time credential presentation |
| Profile | All completed accounts | Save profile | Shared fields and action spacing; password confirmation retained |
| Settings / backup | System administrators | Maintain locations / download backup | Shared form and section rhythm; POST download semantics retained |
| Audit | System administrators | Filter and inspect saved changes | Mobile stacked records; preserve disclosure and code formatting |
| Blocked work | Officials | Review delay | Shared empty state, spacing and next-action links |
| Guidance / official actions | Officials | Save ordered guidance | Preserve dependent choices, rule revision and reorder; normalize form spacing and disclosure controls |

## Component inventory and implementation order

1. Tokens: spacing scale, control height, transition, focus and elevation; existing fonts, green/navy palette and radii.
2. Typography: consolidate later duplicate base adjustments into original selectors; maintain distinct heading/body/helper sizes.
3. Layout: consistent panel/header/body gutters, wide-screen bounds, section separation and mobile edges.
4. Buttons: primary, light/ghost, danger and text links; common heights, padding and visible keyboard focus.
5. Forms: nested labels and standalone labels, input/select/textarea, search, checkbox/radio, disabled/read-only and helper spacing.
6. Badges and alerts: restrained status families, plan statuses, warnings and success feedback.
7. Panels and tables: border-led surfaces; opt-in stacked mobile record tables, with comparison matrices kept scrollable.
8. Dialogs and navigation: existing SweetAlert confirmations; focus restoration/trapping and correct overlay order.
9. Stepper: semantic ordered lifecycle with current stage and explicit exceptional-state explanation.
10. Pages: preserve established form/DOM contracts, deepen clarity through targeted markup changes.

## Audit findings

- Late CSS rules repeat typography already defined earlier, and wide-screen rules resize shared panel headings unnecessarily.
- `--radius-panel` and `--shadow-soft` are referenced but undefined.
- Inputs nested in labels have inconsistent separation outside action panels; standalone fields use inconsistent vertical rhythm.
- Many table surfaces inherit compact Bootstrap padding; operational tables scroll on mobile while concern lists already use cards.
- Assigned status uses a unique purple, and reopened status shares destructive red rather than a review warning.
- Tracking renders the same guest chat twice, with duplicate controls, forms, launcher and hint IDs.
- Messages repeat a contact lookup and visually reorder the inbox through CSS instead of DOM order.
- Floating chat has a higher stacking level than SweetAlert confirmations.
- The sidebar supports Escape but does not trap keyboard focus or isolate background content while open on mobile.
- The detail stepper handles standard stages and reopened reports, but does not explain information requests, referrals, rejection, linked reports or blocks.

## Validation and screenshot review

Before screenshots are preserved under `tests/tmp/ui-before/` (66 existing baseline captures). The isolated browser runner produces after screenshots and computed typography records under `tests/tmp/`.

No live test accounts, concerns, schema changes or test email deliveries are required for this work.

## Completed design system changes

| Area | Implementation |
| --- | --- |
| Spacing | Shared 4/8/12/16/20/24/32/40px tokens; predictable panel gutters, wrapped-label separation, helper spacing and submit separation. Search toolbars retain compact alignment. |
| Typography | Existing Segoe UI and 14/12/16/29px hierarchy retained; duplicate late typography rules consolidated into original selectors; mobile metadata and dashboard captions remain readable. Existing page-title mobile sizes retained. |
| Surfaces | Static panels/cards use borders and no shadow. Floating chat, dropdowns, toasts and the mobile sidebar retain a restrained overlay shadow. Existing control/card radii retained. |
| Buttons | Common 44px standard control height, shared transitions and focus. Compact desktop actions remain smaller; mobile actions have larger touch targets. Secondary controls use quiet surfaces and borders. Green remains the primary action. |
| Fields | Consistent border, radius, font, padding, select arrow clearance, placeholder and focus treatment. Disabled/read-only fields retain distinct appearances; checkboxes use MaintainPro green. |
| Status | Centralized reusable concern and action-plan badges. Assigned and informational states use navy, pending/reopened/blocked use amber, success uses green and rejected uses red. Status text darkened for contrast. Labels remain present alongside color. |
| Tables | Shared cell padding and header treatment. Action plans, personnel plans, accounts, audit records, workload and recurring-issue tables become labeled records on narrow screens. Comparison matrices retain native table structure. |
| Layout | Bounded main content, balanced six-card official dashboard grid, contained grid children and table positioning to prevent tablet document overflow. |

## Components and interaction refinements

- **Concern progress:** new presentation-only `concern-progress.php` uses a semantic ordered list, completed-stage checks, `aria-current="step"`, stage numbers and a status explanation. Submitted through official closure are distinguished. Reopened and information requests return to assessment; blocked work is marked paused. Referred, rejected and linked reports do not claim completed repair stages. Original report data remains read-only.
- **Confirmations:** existing SweetAlert workflows gain a close control, default Cancel focus and protection from accidental outside-click dismissal. Dialogs render above floating chat. Sign-out closes the mobile sidebar before opening its confirmation.
- **Mobile navigation:** opening the sidebar isolates background controls, locks background scrolling and moves focus into navigation. Tab wraps within the menu; Escape closes it and returns focus. Resizing to desktop closes the mobile overlay. Existing sidebar scrolling and red sign-out remain available.
- **Messaging:** inbox precedes creation in actual DOM order; optional concern/action-plan linking is an expandable section. Existing search still finds staff without conversation history. Recent chat limits, show-more scrolling, unread counts and message delivery are retained.
- **Private tracking:** removed the second chat form, launcher and duplicated IDs. Follow-up submission is disabled while tracking/chat status loads, preventing a visible action from silently returning while the request is busy.
- **Alerts and empty states:** retain the shared Bootstrap alert and existing empty-state components; readable helper typography, border-led surfaces and shared action spacing apply throughout. No parallel alert framework was introduced.
- **Workflow context:** section IDs, `data-workflow-section` anchors and saved-position mapping remain intact. Browser tests exercise assessment, assignment, start, progress, delay, resolution and review saves returning to the relevant section.

## Page-by-page results

| Page group | Completed refinement |
| --- | --- |
| Landing/public header | Existing lightweight secondary navigation and filled Sign in retain their hierarchy with matching 44px dimensions, focus and wrapping. Hero/category cards use existing radii and borders instead of missing tokens and decorative rounding. |
| Reporting | Shared field spacing, checkbox focus, helper sizing, select padding, section hierarchy and submit separation improve both public and authenticated three-section forms. Category/keypoint guidance and uploads remain unchanged. |
| Tracking | One private chat UI; reliable follow-up busy state; shared spacing for tracking fields and public result/history sections. Private code handling and reporter access remain unchanged. |
| Public progress and guide | Shared panel/heading/control refinements; existing aggregate privacy and role-specific guidance preserved. |
| Dashboards | Attention banner appears before allowance and statistics. Official metrics use two balanced rows at desktop. Mobile notices keep the description together and place the queue link below it. Secondary workflow guidance is a quiet bordered surface. |
| Concerns/history | Clearer reference/location/date metadata; assigned staff and target time shown to staff where supplied. Contextual reset preserves the own-report/work scope. Existing native links, GET filtering and mobile concern cards preserved. |
| Concern detail | Six-stage accessible progress display with exceptional-state explanations; readable form gutters and buttons, preserved original-record sections, before/after evidence and activity timeline. |
| Personnel pages | Shared progress/resolution form rhythm, readable dashboard metrics, blocked-state explanation and stacked action-plan records. Evidence requirements and official approval gates retained. |
| Reports and insights | Existing recent overview/weekly-analysis grouping and visual summaries retained; shared typography, table padding, surfaces and mobile action-plan/workload/recurrence records refined. Export behavior retained. |
| Messages/chat | Inbox first; optional work linking collapsed; shared search and form dimensions, restrained chat elevation and correct modal stacking. Search, pagination, unread behavior and direct/concern messages retained. |
| Action plans | Reusable status badges, mobile records and corrected weekly-concerns backlink to Reports. Creation, assignment, progress and completion rules unchanged. |
| Accounts | Labeled mobile records replace the forced 620px account table. Shared create/edit/profile fields and actions improve spacing. Temporary credential and password-change gates retained. |
| Authentication | Shared fields, buttons, labels, helper hierarchy and focus apply to login, registration, setup, password change and recovery. Existing layouts and OTP flows retained. |
| Administration/settings/audit/libraries | Shared panel gutters, field rhythm, disclosures and buttons. Audit records stack on mobile; configuration, rule revision, backup and capability boundaries retained. |
| Notifications | Shared typography, spacing and restrained dropdown elevation; existing read/unread actions and destinations retained. |

## CSS cleanup

- Consolidated duplicate late base typography rules into their original selectors.
- Removed redundant mobile typography overrides and unnecessary wide-screen typography/padding variations.
- Removed the inbox/create-panel CSS ordering workaround after correcting DOM order.
- Replaced undefined `--radius-panel` and `--shadow-soft` references with existing valid tokens.
- Removed the forced mobile account-table minimum width.
- Corrected a workflow-card button selector to target its actual link.
- Kept component changes in their existing stylesheet sections; no legacy `styles.css` edits or second design system.

## Modified files

| File | Purpose |
| --- | --- |
| `assets/css/app.css` | Shared tokens, typography, controls, panels, status, responsive records, stepper, overlay and spacing refinements |
| `assets/js/app.js` | Mobile navigation focus/background handling and confirmation refinements |
| `assets/js/public.js` | Tracking follow-up ready/disabled state |
| `includes/components/concern-progress.php` | New semantic lifecycle presentation |
| `complaint.php` | Render the shared lifecycle component |
| `index.php` | Prioritize attention banner |
| `includes/components/complaint-table.php` | Staff assignment/target metadata and contextual reset |
| `includes/components/action-plan-list.php` | Status badges and mobile labels |
| `includes/components/workload-table.php` | Mobile record labels |
| `includes/components/recurring-issues.php` | Mobile record labels |
| `action-plans.php` | Correct weekly concerns destination |
| `my-action-plans.php` | Mobile records and status badges |
| `users.php` | Mobile account labels |
| `audit.php` | Mobile audit labels |
| `messages.php` | DOM order, optional-work disclosure and duplicate query removal |
| `track.php` | Remove duplicate chat markup |
| `tests/browser.cjs` | Six-width page sweep, operational record checks, stepper states, unique chat IDs and keyboard menu checks |
| `docs/ui-refinement-20261005.md` | Audit, implementation and verification record |

## Verification results

`C:\xampp\php\php.exe tools\verify.php --browser` passed:

| Suite | Result |
| --- | --- |
| First-party PHP syntax | 110 files passed |
| SQL access boundary | 108 PHP files checked |
| Domain, storage and feature suites | 867 checks passed across workflow, evidence, privacy, accounts, limits, messaging, planning, database integrity, OTP and capabilities |
| HTTP integration | 958 checks passed |
| Browser | 391 checks passed, including complete workflows and responsive review |
| JavaScript syntax | `app.js`, `public.js` and browser runner passed the installed Node-compatible runtime syntax checks |
| Diff hygiene | `git diff --check` passed |

The password-reset suite deliberately exercises failed delivery and prints an SMTP failure message; the suite passed. All test databases and mail were disposable. No live concerns or accounts were created for verification. No schema, workflow rules or authorization changes were made.

## Responsive and screenshot review

The browser sweep covers 35 page/role views at **1440, 1280, 1024, 768, 430 and 390px** (210 view captures and matching computed typography records). It checks document width and successful rendering at every view, plus stacked operational tables on narrow screens. The existing browser suite also captures evidence, progress forms, guidance, guest tracking, chat, account results and short sidebar scenarios.

Manual screenshot review compared the saved baseline with updated dashboards, concern detail, reports, messaging, account tables and public forms. Findings repaired during review: tablet table labels extending document width, cramped mobile dashboard notices, undersized mobile captions, forced account-table width and search action margin. The final layouts retain the green/navy identity and shared type hierarchy.

The reviewed captures covered the mobile dashboard, concern detail, chat, messages, public form, accounts, and reports. They were generated under ignored `tests/tmp/` with disposable data and were not retained as repository documentation. Rerun the isolated browser suite to generate current captures; use a separate private archive when a release-specific visual baseline must be preserved.

## Accessibility and remaining limits

Navigation remains native links. Keyboard focus is visible; mobile menu focus wraps and returns correctly. Progress has semantic labels and current-stage semantics. Record tables retain column headings in the accessibility tree and show mobile field labels. Status text and labels communicate state without relying solely on color. Existing skip links, live regions, captions and label associations are retained.

Validation uses desktop Chrome emulation and disposable fixtures. Physical-device testing, other browser engines, screen-reader testing and formal WCAG certification were not performed. Baseline screenshots cover 66 existing views rather than a complete six-width before sweep. Some dense comparison tables intentionally retain contained horizontal scrolling. Screenshots are local ignored test artifacts; rerunning the suite refreshes them.
