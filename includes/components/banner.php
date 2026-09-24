<?php
if ($actor['role'] === 'official') {
    $bannerTitle = $metrics['assessment'] . ' concern' . ($metrics['assessment'] === 1 ? ' needs' : 's need') . ' your assessment';
    $bannerText = ($metrics['urgent'] ? $metrics['urgent'] . ' urgent concern(s) awaiting action. ' : '') . 'Review the details and recommend the next step.';
    $bannerUrl = 'complaints.php?tab=assessment'; $bannerAction = 'Review queue'; $bannerIcon = 'clipboard';
} else {
    $bannerTitle = $actor['team'] . ' · ' . $metrics['assigned'] . ' new assignment(s)';
    $bannerText = 'Follow the official recommended action and record the work performed.';
    $bannerUrl = 'complaints.php?tab=work'; $bannerAction = 'Open work queue'; $bannerIcon = 'tool';
}
?>
<div class="attention-banner"><div class="attention-icon"><?= br_icon($bannerIcon) ?></div><div class="attention-copy"><strong><?= h($bannerTitle) ?></strong><p><?= h($bannerText) ?></p></div><a class="link-button" href="<?= h($bannerUrl) ?>"><?= h($bannerAction) ?> <?= br_icon('arrow') ?></a></div>
