<?php
declare(strict_types=1);
$mine=[];
foreach($db['requests']??[] as $r)if((int)($r['requested_by']??0)===(int)user()['id'])$mine[]=['type'=>'feature','r'=>$r,'title'=>name_of($db['features'],(int)$r['feature_id'])];
foreach($db['release_submissions']??[] as $r)if((int)($r['requested_by']??0)===(int)user()['id'])$mine[]=['type'=>$r['type'],'r'=>$r,'title'=>$r['type']==='project'?name_of($db['projects'],(int)$r['target_id']):'Phiên bản #'.(int)$r['target_id']];
usort($mine,static fn($a,$b)=>strcmp((string)$b['r']['created_at'],(string)$a['r']['created_at']));
$pendingMine=count(array_filter($mine,static fn($i)=>ReviewWorkflow::pending($i['r']['status'])));
?>
<div class="ff-view-intro d-flex justify-content-between flex-wrap gap-3"><div><div class="ff-kicker">DEVELOPER WORKSPACE</div><h2>Quy trình kiểm thử & phát hành</h2><p>Theo dõi yêu cầu đã gửi Tester. Khi Tester báo đạt, Admin mới có thể phát hành. Nếu bị trả về, hãy sửa lỗi rồi gửi lại.</p></div><a class="btn primary" href="/?p=submit"><i class="bi bi-plus-lg"></i> Gửi tính năng mới</a></div>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="ff-kpi"><span>Tổng yêu cầu</span><strong><?= count($mine) ?></strong></div></div><div class="col-md-6"><div class="ff-kpi"><span>Chờ Tester / Admin</span><strong><?= $pendingMine ?></strong></div></div></div>
<div class="workflow-steps mb-4"><span class="is-current"><b>1</b> Dev gửi</span><i class="bi bi-arrow-right"></i><span><b>2</b> Tester duyệt</span><i class="bi bi-arrow-right"></i><span><b>3</b> Admin phát hành</span><i class="bi bi-arrow-right"></i><span><b>4</b> Web mẫu</span></div>
<section class="panel table-panel"><div class="table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Dự án / tính năng</th><th>Loại</th><th>Nội dung</th><th>Trạng thái</th><th>Phản hồi kiểm thử</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($mine as $item):$r=$item['r'];$status=ReviewWorkflow::normalize($r['status']); ?>
<tr><td><b><?= h($item['title']) ?></b><small><?= h(substr((string)$r['created_at'],0,16)) ?></small></td><td><?= h($item['type']) ?></td><td><?= h($r['reason']??'') ?></td><td><?= pill(ReviewWorkflow::label($status),ReviewWorkflow::color($status)) ?></td>
<td><?= h($r['tester_note']??'Chưa có') ?><?php if(!empty($r['review_note'])): ?><small>Admin: <?= h($r['review_note']) ?></small><?php endif; ?></td>
<td><?php if($status==='tester_rejected'&&$item['type']==='feature'): ?>
<form method="post" action="/"><?= csrf_field() ?><input type="hidden" name="action" value="workflow_resubmit_feature"><input type="hidden" name="back" value="my_requests"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input name="reason" required minlength="5" maxlength="500" class="form-control form-control-sm mb-2" placeholder="Đã sửa lỗi gì?"><button class="btn primary small">Gửi lại Tester</button></form>
<?php elseif($status==='tester_rejected'||$status==='rejected'): ?><a class="btn secondary small" href="/?p=<?= $item['type']==='project'?'projects':'versions' ?>">Mở và sửa lại</a><?php else: ?><span class="text-secondary small">—</span><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if(!$mine): ?><tr><td colspan="6" class="empty">Chưa có yêu cầu nào từ tài khoản này.</td></tr><?php endif; ?>
</tbody></table></div></section>
