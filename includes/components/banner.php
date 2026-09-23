<?php
if ($actor['role'] === 'official') {
    $bannerTitle = $metrics['assessment'] . ' concern' . ($metrics['assessment'] === 1 ? ' needs' : 's need') . ' your assessment';
    $bannerText = ($metrics['urgent'] ? $metrics['urgent'] . ' urgent concern(s) awaiting action. ' : '') . 'Review the details and recommend the next step.';
    $bannerUrl = 'complaints.php?tab=assessment'; $bannerAction = 'Review queue'; $bannerIcon = 'clipboard';
} elseif ($actor['role'] === 'resident') {
    $bannerTitle = $metrics['resolved'] ? $metrics['resolved'] . ' resolution(s) ready for your verification' : 'Your voice helps improve the community';
    $bannerText = $metrics['resolved'] ? 'Check the completed work. Confirm the outcome or tell us what still needs attention.' : 'Submit a concern, suggest a solution, and follow the barangay’s response.';
    $bannerUrl = $metrics['resolved'] ? 'complaints.php?tab=resolved' : 'complaints.php'; $bannerAction = $metrics['resolved'] ? 'Verify outcome' : 'Track my reports'; $bannerIcon = 'checkCircle';
} else {
    $bannerTitle = $actor['team'] . ' · ' . $metrics['assigned'] . ' new assignment(s)';
    $bannerText = 'Follow the official recommended action and record the work performed.';
    $bannerUrl = 'complaints.php?tab=work'; $bannerAction = 'Open work queue'; $bannerIcon = 'tool';
}
?>
<div class="attention-banner"><div class="attention-icon"><?= br_icon($bannerIcon) ?></div><div class="attention-copy"><strong><?= h($bannerTitle) ?></strong><p><?= h($bannerText) ?></p></div><a class="link-button" href="<?= h($bannerUrl) ?>"><?= h($bannerAction) ?> <?= br_icon('arrow') ?></a></div>
