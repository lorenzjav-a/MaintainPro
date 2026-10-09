    <footer class="main-footer"><span>MaintainPro <?= h(br_app_config()['version']) ?> · Community Concern &amp; Resolution Management</span><a href="user-guide.php">User Guide</a></footer>
  </main>
  <?php require __DIR__ . '/mobile.php'; ?>
</div>
<?php if ($page !== 'track') require dirname(__DIR__) . '/components/chat-widget.php'; ?>
<template id="workflow-help"><div class="text-start"><p><strong>1. Resident:</strong> report anonymously and save the private tracking code.</p><p><strong>2. Official:</strong> assess the category and priority, save the official recommendation, then assign a personnel account.</p><p><strong>3. Personnel:</strong> start assigned work, record updates, and submit the resolution.</p><p><strong>4. Official:</strong> review the evidence, close the concern, or reopen it for further work.</p><hr><p class="small text-muted mb-0">Your account determines which concerns and actions are available. Officials manage personnel accounts and teams. Saved concerns and photos remain available after sign-out. Personnel receive email assignment notifications.</p></div></template>
</body>
</html>
