# BarangayResolve prototype

A working PHP prototype based on the supplied BarangayResolve project brief. It includes a guided demo and a saved SQLite workspace with real local accounts.

## Run locally

The project is in C:\xampp\htdocs\MaintainPro.

1. Start **Apache** from the XAMPP Control Panel.
2. Open **http://localhost/MaintainPro/**.

That URL is the only entry point you need. On first use, it guides you through creating the first barangay-official account. Residents can then register themselves, while officials create personnel and additional official accounts. Choose **Explore the prototype** on the sign-in page to enter the fictional demo immediately.

Alternatively, from the project folder run:

    C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php

Then open **http://127.0.0.1:8080/**. Use PHP 8.1+ with mbstring, sessions, and image metadata support (included in this XAMPP installation).

## Stack

- PHP for authentication, role-scoped API responses, validation, and workflow rules.
- SQLite for saved user accounts, complaint records, history, and optimistic concurrency checks.
- HTML and custom CSS for the interface.
- Bootstrap 5.3.3 for layout, forms, and accessible modal dialogs.
- SweetAlert2 11.14.5 for confirmations, errors, and success notifications.
- Plain JavaScript for interaction with the PHP API.

Bootstrap and SweetAlert are vendored in assets/vendor; no Node.js, build step, CDN connection, or separate database server is required. PHP creates the SQLite database on first use.

## Fastest access

- Double-click **Open BarangayResolve.cmd** to start the local app and open it in your browser.
- Open **http://localhost/MaintainPro/**.
- Select **Explore the prototype** for instant access to fictional sample data.
- On a phone-sized screen, use the fixed bottom bar for Overview, Complaints, the primary action, History, and More.
- Every complaint displays its current status and the next step expected from the signed-in role.

## Demonstrate the complete workflow

1. Use **Explore as → Resident**. The demo resident is **Alex Santos**.
2. Select **Report a concern**, enter the details, and optionally add a suggestion and photo.
3. Switch to **Barangay official**. Open the new complaint, set category and priority, enter an official recommended action, and **Save assessment**.
4. Select a responsible team and **Assign complaint**.
5. Switch to **Barangay personnel**, then choose that same team.
6. Open the complaint, select **Accept & start work**, add progress notes, and record a resolution with optional completion evidence.
7. Switch back to **Resident**. Select **Yes, resolved** or enter feedback and select **Problem still exists**.
8. Inspect **Resolution history** and, as an official, **Reports & insights** or **Solution library**.

All sample names, complaints, locations, and records are fictional. Dates are relative to the day a new demo session is created.

## Included

- Reporting with photo preview and optional resident suggested solution.
- Separate official recommendation, assessment notes, category, and priority.
- Assignment to five selectable teams.
- Server-enforced role and status transitions.
- Progress timeline with actors, timestamps, notes, and retained completion evidence.
- Resolution, resident verification, feedback, and reassessment after reopening.
- Return for information and resubmission, rejection with reason, referral with receiving office.
- Search, category/priority/status filters, role-scoped lists, dashboard, history, and CSV export.
- Category-based matches to previous verified cases; an official can copy a prior recommendation as a draft before reviewing and saving it.
- Responsive layout, keyboard-accessible Bootstrap dialogs, and visible focus indicators.
- SweetAlert confirmations for verification, reopening, outcomes, and resetting sample data.
- Registration and sign-in for saved resident accounts.
- Official account management for personnel teams and other officials.
- Saved SQLite complaints that remain after sign-out.
- Password hashing, login throttling, session revocation on password change or account deactivation, CSRF protection, and conflict detection for simultaneous edits.

## Prototype boundaries

This is a local demonstration, **not a production deployment**.

- The freely switchable **demo** remains separate from the saved workspace and is not authentication.
- Demo complaints and photos use the current PHP session. Saved accounts and their records use SQLite.
- There is no email/SMS delivery, password-reset email, identity verification, or public transparency dashboard.
- Images accept JPEG, PNG, or WebP, up to 1 MB and 20 megapixels. The server validates image contents. Total session data is limited to 12 MB.
- Recommendations are written and approved by the official. Similar cases use category matching, not AI.
- CSV exports include this session's complaint data and neutralize spreadsheet formula prefixes.
- Average resolution time includes currently Resolved and Verified cases. Reopened, referred, and rejected complaints are excluded. Repeated categories do not prove that a specific problem recurred.

## Verification

    C:\xampp\php\php.exe tests\workflow.php
    C:\xampp\php\php.exe tests\store.php

Checks cover the full workflow, accounts, role restrictions, invalid transitions, atomic validation, photo validation, reopening, additional information, rejection, referral, evidence history, persistent storage, password behavior, and simultaneous-edit protection.

## Project files

- index.php: HTML shell and local dependencies.
- app.js: interface and PHP API integration.
- styles.css: visual design and responsive styling.
- api.php: session-scoped JSON API and CSV reports.
- auth.php and login.php: account access and first-time setup.
- includes/domain.php: workflow and fictional seed records.
- includes/store.php: SQLite persistence and account management.
- includes/bootstrap.php: session setup, storage selection, and response headers.
- tests/workflow.php, tests/store.php, and tests/http.php: regression checks.

The shared ChatGPT conversation could not be retrieved. The pasted BarangayResolve brief is the requirements source; no additional historical decisions are implied.
