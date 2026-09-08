# Prototype scope and traceability

Source: the user's pasted BarangayResolve brief and instruction to use PHP, HTML, CSS, Bootstrap, and SweetAlert.

| Requested MVP module | Implemented demonstration |
| --- | --- |
| Complaint submission | Title, category, description, location, optional photo and resident suggestion |
| Assessment and categorization | Official category and priority changes, assessment notes, official recommendation |
| Proposed solution and recommended action | Separate resident suggestion and official action |
| Assignment and status tracking | Select a team, accept work, add progress notes, inspect timestamped timeline |
| Resolution and resident verification | Work performed and photo, feedback, verified closure or reopening |
| Dashboard and history | Overview, register, outcome history, category/location/status reports, CSV |
| Optional solution knowledge base | Verified category matches; previous recommendation copied as an editable draft |
| Additional statuses | Return for information and resubmit, reject with reason, refer with receiving office |

Normal journey:

Submitted → Under Review → Assigned → In Progress → Resolved → Verified

Unsuccessful resident verification:

Resolved → Reopened → Under Review → Assigned → In Progress → Resolved → Verified

Prototype decisions: fictional sample data; Alex Santos as demo resident; Maria Dela Cruz as demo official; five selectable teams; PHP-session persistence; role switching instead of authentication. These are implementation assumptions, not claimed historical decisions.

Future features remain out of scope: SMS/email, maps/heatmaps, QR codes, mobile apps, AI suggestions, automated recurrence detection, public transparency, LGU integration, and advanced analytics.
