<?php
declare(strict_types=1);
require_once __DIR__.'/view.php';
require_once dirname(__DIR__).'/vendor/dompdf/autoload.inc.php';

final class ConcernReportPdf
{
    public static function generate(array $report, ?DateTimeImmutable $generated = null): string
    {
        $generated = ($generated ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Manila'));
        $html = self::html($report,$generated);
        // Reject before layout if the hosting account cannot safely hold this document.
        ConcernReportCapacity::check(count($report['preview']['items']),strlen($html));
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'maintainpro-pdf-cache';
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('PDF font cache is unavailable.');
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled',false);
        $options->set('isPhpEnabled',false);
        $options->set('isJavascriptEnabled',false);
        $options->set('isFontSubsettingEnabled',true);
        $options->set('defaultFont','DejaVu Sans');
        $options->set('chroot',[dirname(__DIR__).'/assets/images',dirname(__DIR__).'/vendor/dompdf']);
        $options->set('tempDir',$directory);
        $options->set('fontCache',$directory);
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->setPaper('A4','landscape');
        $pdf->loadHtml($html,'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans','normal');
        $canvas->page_text(36,16,'MaintainPro | Concerns Report & Insights',$font,7,[.32,.42,.46]);
        $canvas->page_text(36,568,'MaintainPro | Generated from authorized records | '.$generated->format('d M Y, g:i A').' PHT',$font,7,[.32,.42,.46]);
        $canvas->page_text(710,568,'Page {PAGE_NUM} of {PAGE_COUNT}',$font,7,[.32,.42,.46]);
        return $pdf->output();
    }

    private static function text(mixed $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u','',(string)$value) ?? '';
        // Break long unbroken references without losing characters or creating markup.
        $value = preg_replace_callback('/\S{34,}/u', static fn($m) => implode("\u{200B}",mb_str_split($m[0],32)), $value) ?? '';
        return nl2br(h($value));
    }

    public static function html(array $report, DateTimeImmutable $generated): string
    {
        $m = $report['metrics']; $rows = $report['preview']['items'];
        $logo = 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(dirname(__DIR__).'/assets/images/favicon.svg'));
        ob_start(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Concerns Report &amp; Insights</title><style>
@page { margin: 32pt 36pt 42pt; }
body { font-family: 'DejaVu Sans'; font-size: 9pt; line-height: 1.5; color: #102b32; }
h1 { font-size: 23pt; margin: 0 0 3pt; } h2 { font-size: 13pt; color: #105440; margin: 20pt 0 8pt; page-break-after: avoid; }
h3 { font-size: 10pt; margin: 8pt 0; page-break-after: avoid; } p { margin: 4pt 0 8pt; }
.brand { width: 100%; border-bottom: 3pt solid #16745d; margin-bottom: 12pt; } .brand td { border: 0; padding: 0 0 12pt; }
.logo { width: 46pt; height: 46pt; margin-right: 12pt; } .brand-name { font-size: 18pt; font-weight: bold; } .brand-name span { color: #16745d; }
.subtitle,.muted { color: #526b74; } .meta { text-align: right; font-size: 8pt; }
.filters { background: #f3f8f5; padding: 10pt 12pt; border-left: 3pt solid #16745d; } .filters p { margin: 2pt 0; }
.stats { width: 100%; border-spacing: 5pt; margin: 0 -5pt; } .stats td { background: #d9f4e8; padding: 10pt; border: 0; width: 16.66%; } .number { display: block; font-size: 21pt; font-weight: bold; color: #105440; }
table { width: 100%; border-collapse: collapse; } th { background: #102b32; color: #fff; text-align: left; font-size: 8pt; padding: 7pt; }
td { vertical-align: top; border-bottom: .5pt solid #dce8e1; padding: 7pt; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
.striped tbody tr:nth-child(even) { background: #f3f8f5; }
.distributions td { padding: 3pt 7pt; font-size: 8pt; } .distributions th { background: #16745d; } .insights-section { page-break-before: always; }
.detail-section { page-break-before: always; } .concern-heading { border-bottom: 1pt solid #16745d; padding-bottom: 6pt; margin-top: 16pt; }
.detail-meta td { width: 50%; padding: 4pt 8pt; background: #f3f8f5; font-size: 9pt; } .detail-meta { margin-bottom: 7pt; }
.description { margin: 6pt 0 14pt; } .concern-detail { page-break-inside: avoid; } .note { font-size: 8pt; color: #526b74; } .empty { padding: 20pt; background: #f3f8f5; border: 1pt solid #dce8e1; }
</style></head><body>
<table class="brand"><tr><td><img class="logo" src="<?= h($logo) ?>" alt="MaintainPro"></td><td><div class="brand-name">Maintain<span>Pro</span></div><div class="subtitle">Community Concern Management Report</div></td><td class="meta">Generated <?= h($generated->format('F j, Y · g:i A')) ?> PHT<br>Private - authorized official use<br>Submission-date reporting</td></tr></table>
<h1>Concerns Report &amp; Insights</h1><p class="subtitle">A filtered view of community concerns, outcomes, and assigned response.</p>
<h2>Applied filters &amp; reporting period</h2><div class="filters"><?php foreach($report['labels'] as $label=>$value): ?><p><strong><?= h($label) ?>:</strong> <?= self::text($value) ?></p><?php endforeach ?><?php if($report['filters']['start']==='' && $report['filters']['end']===''): ?><p><strong>Reporting period:</strong> All submission dates</p><?php endif ?></div>
<h2>Executive summary</h2><table class="stats"><tr><?php foreach(['Matching concerns'=>$m['total'],'Open / active'=>$m['pending'],'Official-closed'=>$m['verified'],'Awaiting closure review'=>$m['resolved'],'Pending assessment'=>$m['assessment'],'High / Urgent'=>$m['highUrgent']] as $label=>$number): ?><td><span class="number"><?= (int)$number ?></span><?= h($label) ?></td><?php endforeach ?></tr></table>
<p class="note">Open / active follows the existing workflow and includes work awaiting official closure. Official-closed = Verified. Pending assessment = Submitted, Under Review or Reopened. High / Urgent includes all matching records at those priorities. Average resolution time: <?= h($m['average']) ?> days (currently Resolved or Verified records with a resolution date).</p>
<section class="insights-section"><h2>Reports &amp; insights</h2>
<table class="distributions striped"><thead><tr><th>Distribution</th><th>Category / value</th><th>Matching concerns</th></tr></thead><tbody>
<?php foreach(['category'=>'Category','status'=>'Status','priority'=>'Priority','type'=>'Concern type','location'=>'Purok / Sitio'] as $key=>$label): ?>
<?php foreach(array_slice($report['groups'][$key],0,8) as $row): ?><tr><td><?= h($label) ?></td><td><?= self::text($key==='status'?br_status_label($row['label']):$row['label']) ?></td><td><?= (int)$row['count'] ?></td></tr><?php endforeach ?>
<?php if(count($report['groups'][$key])>8): ?><tr><td><?= h($label) ?></td><td>Other values (<?= count($report['groups'][$key])-8 ?> groups)</td><td><?= array_sum(array_column(array_slice($report['groups'][$key],8),'count')) ?></td></tr><?php endif ?>
<?php endforeach ?>
<?php if(!$rows): ?><tr><td colspan="3">No matching records. No unrelated concerns were included.</td></tr><?php endif ?></tbody></table>
<p class="note">Summaries use all <?= (int)$m['total'] ?> matching concerns. Ranked distributions show up to eight leading values plus an explicit remainder. Independent weekly plans, global recurrence history and overall team capacity are not part of this filtered report.</p></section>
<section class="detail-section"><h2>Detailed filtered concerns</h2><p><?= (int)$m['total'] ?> matching concerns. Every matching record appears in the register and full details below. Reporter identities, tracking codes and internal notes are excluded.</p>
<?php if(!$rows): ?><div class="empty"><strong>No concerns match the applied filters.</strong><p>This is an empty report; it does not fall back to all concerns.</p></div><?php else: ?>
<table class="striped"><thead><tr><th>Concern ID</th><th>Submitted</th><th>Category / type</th><th>Priority</th><th>Status</th><th>Assigned personnel</th></tr></thead><tbody>
<?php foreach($rows as $c): ?><tr><td><?= h($c['id']) ?></td><td><?= h(br_date($c['createdAt'])) ?></td><td><?= self::text($c['category']) ?><br><span class="muted"><?= self::text($c['concernType'] ?: 'Legacy / unspecified') ?></span></td><td><?= h($c['priority']) ?></td><td><?= h(br_status_label($c['status'])) ?></td><td><?= self::text($c['assignedName'] ?: 'Unassigned') ?></td></tr><?php endforeach ?></tbody></table>
<h2>Concern details - locations &amp; descriptions</h2>
<?php foreach($rows as $c): ?><article class="concern-detail">
<h3 class="concern-heading"><?= h($c['id']) ?> | <?= self::text($c['title']) ?></h3>
<table class="detail-meta"><tr><td><strong>Submitted:</strong> <?= h(br_date($c['createdAt'],true)) ?><br><strong>Category / type:</strong> <?= self::text($c['category'].' / '.($c['concernType'] ?: 'Legacy / unspecified')) ?></td><td><strong>Status / priority:</strong> <?= h(br_status_label($c['status']).' / '.$c['priority']) ?><br><strong>Team / personnel:</strong> <?= self::text(($c['team'] ?: 'Unassigned').' / '.($c['assignedName'] ?: 'Unassigned')) ?></td></tr></table>
<p><strong>Location:</strong> <?= self::text($c['location'] ?: 'Not recorded') ?></p>
<?php if($c['keyPoints']): ?><p><strong>Key points:</strong> <?= self::text(implode(' · ',$c['keyPoints'])) ?></p><?php endif ?>
<div class="description"><strong>Reported description (<?= h($c['id']) ?>):</strong><p><?= self::text($c['description'] ?: 'No additional description was provided.') ?></p></div>
</article><?php endforeach; endif ?></section>
</body></html>
<?php return (string)ob_get_clean();
    }
}
