<?php $allowance = $allowance ?? br_store()->submissionAllowance($actor['id']); ?>
<section class="info-callout submission-allowance" aria-label="Daily reporting allowance">
  <div><strong><?= (int)$allowance['used'] ?> of <?= (int)$allowance['limit'] ?> concern submissions used today.</strong>
  <p class="form-text mb-0"><?= $allowance['remaining'] ? 'Identified and anonymous reports share this allowance. It resets at midnight, Asia/Manila.' : 'You have reached the maximum of 3 concern submissions for today. You may submit another concern tomorrow.' ?></p></div>
  <?php if ($page !== 'new-complaint'): ?><a class="btn btn-light btn-sm" href="complaints.php?scope=mine">My reported concerns</a><?php endif ?>
</section>
