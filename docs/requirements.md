# Requirements and scope

Source: the user's pasted community complaint management brief and instruction to use PHP, HTML, CSS, Bootstrap, and SweetAlert. The project's final name is MaintainPro.

| Requested MVP module | Implemented behavior |
| --- | --- |
| Complaint submission | Title, category, description, location, optional photo and resident suggestion |
| Assessment and categorization | Official category and priority changes, assessment notes, official recommendation |
| Proposed solution and recommended action | Separate resident suggestion and official action |
| Assignment and status tracking | Select a team, accept work, add progress notes, inspect timestamped timeline |
| Resolution and resident verification | Work performed and photo, feedback, verified closure or reopening |
| Dashboard and history | Overview, register, outcome history, category/location/status reports, CSV |
| Optional solution knowledge base | Verified category matches; previous recommendation copied as an editable draft |
| Additional statuses | Return for information and resubmit, reject with reason, refer with receiving office |
| User management | First-official setup, resident registration, sign-in, role-based accounts, personnel teams, profile/password updates, deactivation |
| Persistent records | MySQL/MariaDB complaints and histories with concurrent-edit conflict detection |
| Page architecture | Dedicated PHP pages, reusable layouts, native desktop/mobile links, URL filters and server-side page authorization |

Normal journey:

Submitted → Under Review → Assigned → In Progress → Resolved → Verified

Unsuccessful resident verification:

Resolved → Reopened → Under Review → Assigned → In Progress → Resolved → Verified

The current workspace uses real authenticated accounts and MySQL/MariaDB persistence, with five assignable personnel teams. There are no seeded demo accounts or role-switching controls. The first official is created during setup, public registration creates residents, and officials issue other accounts with temporary passwords. Separate browser profiles provide isolated sessions for local workflow testing.

Password-recovery email is implemented. Future features remain out of scope: complaint notifications by SMS/email, maps/heatmaps, QR codes, mobile apps, AI suggestions, automated recurrence detection, public transparency, LGU integration, and advanced analytics.
