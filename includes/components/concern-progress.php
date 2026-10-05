<?php
// Presentation only: current status drives the stage; exceptions never imply closure.
$progressLabels = ['Submitted', 'Assessment', 'Assignment', 'Work', 'Resolution', 'Official review'];
$progressIndex = match ($c['status']) {
    'Submitted' => 0,
    'Under Review', 'Reopened', 'Returned for Information' => 1,
    'Assigned' => 2,
    'In Progress' => 3,
    'Resolved' => 4,
    'Verified' => 5,
    default => null,
};
$progressExplanation = match ($c['status']) {
    'Submitted' => 'The report is saved and waiting for barangay assessment.',
    'Under Review' => 'The barangay is assessing the report and preparing an assignment.',
    'Assigned' => 'Personnel have been assigned. Work can begin after reviewing the instructions.',
    'In Progress' => 'Personnel are recording work and evidence.',
    'Resolved' => 'Personnel have recorded a resolution. Official review is still required.',
    'Verified' => 'The barangay reviewed the resolution and closed the concern.',
    'Reopened' => 'This concern returned to assessment. Earlier work remains in the timeline.',
    'Returned for Information' => 'Assessment is waiting for the reporter to provide more information.',
    'Rejected' => 'The barangay rejected this report. The normal work stages do not apply to this outcome.',
    'Referred to Another Office' => 'This report was referred to another office. Local work stages do not indicate that it was repaired.',
    'Linked to Primary' => 'Work follows the linked primary concern. This original report remains recorded separately.',
    default => 'Review the current status and activity timeline for the latest update.',
};
$progressBlocked = !empty($c['blocked']['active']);
if ($progressBlocked) $progressExplanation = 'Work is blocked or delayed. Review the recorded reason and official instructions before continuing.';
?>
<section class="concern-progress" aria-label="Concern progress">
  <ol class="workflow-steps">
    <?php foreach ($progressLabels as $i => $label):
      $completed = $progressIndex !== null && ($i < $progressIndex || $c['status'] === 'Verified');
      $current = $i === $progressIndex && $c['status'] !== 'Verified';
    ?>
    <li class="workflow-step<?= $completed ? ' complete' : ($current ? ' current' : '') ?><?= $current && ($progressBlocked || in_array($c['status'], ['Reopened', 'Returned for Information'], true)) ? ' paused' : '' ?>"<?= $current ? ' aria-current="step"' : '' ?>>
      <span class="step-circle" aria-hidden="true"><?= $completed ? br_icon('check') : $i + 1 ?></span>
      <span><?= h($label) ?><span class="visually-hidden"><?= $completed ? ' — complete' : ($current ? ' — current stage' : ' — normal workflow stage') ?></span></span>
    </li>
    <?php endforeach ?>
  </ol>
  <p class="workflow-explanation"><?= h($progressExplanation) ?></p>
</section>
