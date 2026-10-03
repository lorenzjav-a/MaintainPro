<?php
declare(strict_types=1);
require __DIR__.'/includes/public-layout.php';
$actor=br_actor();
if ($actor) {
    require_once __DIR__.'/includes/page.php';
    extract(br_page('user-guide'));
    require __DIR__.'/includes/layout/header.php';
    br_heading('User Guide','Follow the steps for your role. Most daily work starts with a concern.');
} else {
    br_public_header('User Guide');
    br_heading('MaintainPro User Guide','Simple steps for reporting, responding to, and resolving barangay concerns.');
}
?>
<nav class="guide-jumps panel" aria-label="Guide sections"><a href="#reporters">Residents &amp; Guests</a><a href="#officials">Officials</a><a href="#personnel">Personnel</a><a href="#administrators">System Admin</a></nav>
<div class="guide-grid">
<section class="panel guide-card" id="reporters"><div class="panel-header"><h2 class="panel-title">Residents &amp; Guests</h2></div><div class="panel-body"><h3 class="section-title">Report a concern</h3><ol><li>Choose <strong>Report Concern</strong>.</li><li>Choose the category, concern type, and key points.</li><li>Enter the location and helpful details.</li><li>Add a photo when it is safe.</li><li>Submit the concern.</li><li>Guests must save the reference number and private tracking code.</li></ol><h3 class="section-title">Track a concern</h3><ol><li>Choose <strong>Track Concern</strong>.</li><li>Enter the reference number and tracking code.</li><li>View the status, latest updates, and messages.</li></ol></div></section>
<section class="panel guide-card" id="officials"><div class="panel-header"><h2 class="panel-title">Barangay Officials</h2></div><div class="panel-body"><ol><li>Open new concerns from the dashboard.</li><li>Review the report, evidence, priority advice, and possible duplicates.</li><li>Assess the concern and record the recommended action.</li><li>Assign responsible personnel and a target date.</li><li>Monitor updates and respond to messages.</li><li>Review completed work and evidence.</li><li>Close the concern or reopen it with clear instructions.</li></ol><p class="form-text">Reports, planning, solution rules, and other occasional functions are under More Tools.</p></div></section>
<section class="panel guide-card" id="personnel"><div class="panel-header"><h2 class="panel-title">Barangay Personnel</h2></div><div class="panel-body"><ol><li>Open <strong>My Work</strong>.</li><li>Choose an assigned concern and review the official recommendation.</li><li>Choose <strong>Start Work</strong> and upload evidence.</li><li>Add progress updates as work continues.</li><li>Report a delay through <strong>Mark work as blocked</strong> when help is needed.</li><li>Upload completion evidence and mark the work complete.</li><li>Respond only to concern messages you are authorized to view.</li></ol></div></section>
<section class="panel guide-card" id="administrators"><div class="panel-header"><h2 class="panel-title">System Admin</h2></div><div class="panel-body"><p>Use <strong>Administration</strong> for occasional system management:</p><ul><li>Add and manage user accounts.</li><li>Maintain approved Purok and Sitio locations.</li><li>Review audit history and system capabilities.</li><li>Download protected record backups.</li><li>Update workspace settings and email configuration.</li></ul><p class="form-text">These tools support the concern workflow and are separate from daily review and assignment.</p></div></section>
</div>
<?php if ($actor) require __DIR__.'/includes/layout/footer.php'; else br_public_footer(); ?>
