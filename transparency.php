<?php
declare(strict_types=1);
require __DIR__.'/includes/public-layout.php';
$statistics=br_store()->publicStatistics(); $summary=$statistics['summary'];
br_public_header('Community progress');
br_heading('Community progress','Aggregated concern counts and service progress. Updated from saved reports.');
?>
<div class="row g-3 mb-4">
<?php foreach(['this_month'=>'Submitted this month','under_review'=>'Under review','in_progress'=>'Assigned / in progress','resolved'=>'Resolved / closed'] as $field=>$label): ?><div class="col-sm-6 col-lg-3"><section class="panel p-4 h-100"><h2 class="section-title"><?= h($label) ?></h2><strong><?= (int)$summary[$field] ?></strong></section></div><?php endforeach ?>
</div>
<p class="info-callout">Resolution percentage: <strong><?= $summary['total'] ? (int)round($summary['resolved']/$summary['total']*100) : 0 ?>%</strong> of <?= (int)$summary['total'] ?> reports. Linked reports remain counted as reports; they are not additional work assignments.</p>
<div class="row g-4"><?php foreach(['categories'=>'Common concern categories','puroks'=>'Concerns by Purok / Sitio','months'=>'Monthly submissions'] as $key=>$title): ?><div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><h2 class="panel-title"><?= h($title) ?></h2></div><div class="panel-body"><?php foreach($statistics[$key] as $row): ?><div class="insight-row"><span><?= h($row['label'] ?? 'Unspecified') ?></span><strong><?= (int)$row['count'] ?></strong></div><?php endforeach ?><?php if(!$statistics[$key]): ?><p>No reports yet.</p><?php endif ?></div></section></div><?php endforeach ?></div>
<p class="form-text mt-4">Counts cover saved reports, including guest and anonymous reports. Purok groups use the barangay location registry; typed addresses are grouped as unspecified.</p>
<?php br_public_footer(); ?>
