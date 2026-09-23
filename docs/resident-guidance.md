# Temporary guidance for residents

The three generated steps are practical actions for the reporting resident while waiting for the barangay response. They appear under **While you wait** as a numbered, read-only list. There is no preferred solution, radio selection or request for the resident to choose a staff task.

For example, illegal-dumping guidance tells residents to keep children and pets away from the pile, cover their own household rubbish, and avoid adding waste to the affected spot. Residents are not asked to inspect waste, organize collection or perform hazardous cleanup. Electrical hazards direct residents away from the hazard and toward utility/emergency assistance.

## Complete flow

1. Category, concern type and key points generate three temporary steps from the curated catalog.
2. Residents submit the concern without choosing any step. The server generates the guidance independently and ignores submitted `selectedSuggestion`, suggestion text and client-provided guidance.
3. The saved `residentGuidance` array is returned with the receipt and included in the tracking-details download.
4. Private tracking returns the same public guidance snapshot. It does not expose private location, staff recommendations, case notes, identities or photos.
5. Staff can expand a reference section showing what guidance was shared. Assessment, the official action plan, assignment, work evidence and closure remain separate. No record claims that the resident performed the steps.

The receipt and tracking page identify the guidance as based on the original report. Official edits and later rule changes do not rewrite that historical snapshot. Residents should follow current instructions from the barangay or emergency responders.

## Solution Library and compatibility

Officials maintain three resident-directed steps per concern type in the Solution Library. Its instructions distinguish temporary household actions from inspections, repairs and staff responsibilities. Electrical hazard selections use the built-in protective guidance even if an override exists.

The existing `solution_rules.actions` JSON column now saves an object with `purpose: resident_temporary_guidance_v1` and a three-string `steps` array. Earlier arrays contain staff-action suggestions, so they are preserved in storage but ignored by the resident guidance service. Built-in guidance is used until an official rewrites and saves that type. Outdated editor forms cannot publish without the new purpose marker. Resetting a type restores the built-in resident guidance.

No schema migration or live-data reset is required. Existing concern suggestions and reporter preferences are retained in a collapsed legacy section for historical reference. They are never relabeled or exposed through public tracking as resident guidance. New reports have no selected preference; the old `suggestion` field remains empty for export compatibility. CSV export labels that older column as legacy and adds a separate temporary resident guidance column.

The public API uses `guidance` and returns `residentGuidance`. The previous `suggestions` endpoint remains compatible with older open tabs but returns the new resident guidance. It cannot restore the old selection behavior on the server.

## Safety references used for the built-in wording

- Avoiding floodwater and its hazards: [CDC floodwater safety](https://www.cdc.gov/floods/safety/floodwater-after-a-disaster-or-emergency-safety.html).
- Keeping away from fallen power lines and objects they touch: [American Red Cross power outage safety](https://www.redcross.org/get-help/how-to-prepare-for-emergencies/types-of-emergencies/power-outage.html).
- Using a safe water source and following local water-use advisories: [CDC drinking-water advisories](https://www.cdc.gov/water-emergency/about/drinking-water-advisories-an-overview.html).

These references support the protective guidance; the app does not diagnose hazards or automatically certify custom text entered by an official. Rules remain curated, without an external AI service.

## Verification

`tools/verify.php` exercises catalog coverage, forged and outdated selections, legacy rules, saved guidance snapshots, tracking privacy, staff assessment/assignment/work and account permissions using disposable databases. `tests/browser.php` checks the read-only list, key-point updates, submission without a selection, receipt/tracking guidance and mobile layout using isolated browser sessions and local email capture.
