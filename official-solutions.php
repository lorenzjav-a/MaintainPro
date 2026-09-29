<?php
declare(strict_types=1);
require __DIR__.'/includes/page.php';
extract(br_page('official-solutions',['official']));
$category=br_query('category',array_key_first(ConcernCatalog::TYPES));
if (!isset(ConcernCatalog::TYPES[$category])) $category=array_key_first(ConcernCatalog::TYPES);
$type=br_query('type',ConcernCatalog::TYPES[$category][0]);
if (!in_array($type,ConcernCatalog::TYPES[$category],true)) $type=ConcernCatalog::TYPES[$category][0];
$point=br_query('keypoint',ConcernCatalog::POINTS[$category][0]);
if (!in_array($point,ConcernCatalog::POINTS[$category],true)) $point=ConcernCatalog::POINTS[$category][0];
$rules=br_store()->officialRules($actor['id'],$category,$type,$point);
$slots=array_column($rules,null,'sort_order');
require __DIR__.'/includes/layout/header.php';
br_heading($pageTitle,'Manage up to three staff actions for each category, concern type and keypoint.','<a class="btn btn-light" href="solutions.php">Resident guidance library</a>');
?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Choose a keypoint</h2></div><div class="panel-body">
<form method="get" class="row g-3 align-items-end">
<div class="col-md-4"><label class="form-label">Category<select class="form-select" name="category"><?php br_options(array_keys(ConcernCatalog::TYPES),$category); ?></select></label></div>
<div class="col-md-4"><label class="form-label">Concern type<select class="form-select" name="type"><?php br_options(ConcernCatalog::TYPES[$category],$type); ?></select></label></div>
<div class="col-md-4"><label class="form-label">Keypoint<select class="form-select" name="keypoint"><?php br_options(ConcernCatalog::POINTS[$category],$point); ?></select></label></div>
<div class="col-12"><button class="btn btn-light" type="submit">Load actions</button><p class="form-text mt-2">After choosing a different category, load it to refresh its concern types and keypoints.</p></div>
</form></div></section>
<section class="panel mt-4"><div class="panel-header"><div><h2 class="panel-title"><?= h($type.' / '.$point) ?></h2><p class="panel-subtitle"><?= h($category) ?></p></div></div><div class="panel-body">
<p>These actions appear in Weekly Top Concerns. Resident guidance stays in its own library. Move actions up or down to change their suggested order.</p>
<form method="post" action="api.php" data-action="save_official_rules">
<input type="hidden" name="category" value="<?= h($category) ?>"><input type="hidden" name="concernType" value="<?= h($type) ?>"><input type="hidden" name="keypoint" value="<?= h($point) ?>"><input type="hidden" name="revision" value="<?= h(ComplaintStore::ruleRevision($rules)) ?>">
<?php for($i=1;$i<=3;$i++): $rule=$slots[$i] ?? []; ?>
<fieldset class="case-section" data-rule-slot="<?= $i ?>"><legend class="form-label">Suggested official action <?= $i ?></legend>
<label class="form-label">Action<textarea class="form-control" name="action<?= $i ?>" maxlength="700" rows="3"><?= h($rule['action_text'] ?? '') ?></textarea></label>
<label class="form-check"><input type="checkbox" class="form-check-input" name="active<?= $i ?>" value="1"<?= !empty($rule['active'])?' checked':'' ?>><span class="form-check-label">Active</span></label>
<div class="d-flex gap-2 mt-2"><?php if($i>1): ?><button type="button" class="btn btn-light btn-sm" data-move-rule="-1">Move up</button><?php endif ?><?php if($i<3): ?><button type="button" class="btn btn-light btn-sm" data-move-rule="1">Move down</button><?php endif ?></div></fieldset>
<?php endfor ?>
<button class="btn btn-primary" type="submit">Save official actions</button>
</form></div></section>
<?php require __DIR__.'/includes/layout/footer.php'; ?>
