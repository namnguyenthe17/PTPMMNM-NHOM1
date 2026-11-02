<?php
/** Admin only, second approval step after Tester passes. */
declare(strict_types=1);
if(!is_admin()){http_response_code(403);echo 'Không đủ quyền';return;}
$items=[];
foreach($db['requests']??[] as $r){
    $f=Policy::find($db['features'],(int)$r['feature_id']);if(!$f)continue;
    $items[]=['type'=>'feature','record'=>$r,'name'=>$f['name'],'project'=>(int)$f['project_id'],'detail'=>strtoupper($r['environment']).' · '.($r['enabled']?'Bật':'Tắt')];
}
foreach($db['release_submissions']??[] as $r){
    $name=$r['type']==='project'?name_of($db['projects'],(int)$r['target_id']):'Phiên bản #'.(int)$r['target_id'];
    $items[]=['type'=>$r['type'],'record'=>$r,'name'=>$name,'project'=>(int)$r['project_id'],'detail'=>$r['type']==='project'?'Đưa dự án lên Web mẫu':strtoupper($r['environment']).' · Phát hành bản nháp'];
}
usort($items,static fn($a,$b)=>strcmp((string)$b['record']['created_at'],(string)$a['record']['created_at']));
$ready=count(array_filter($items,static fn($i)=>$i['record']['status']==='tester_approved'));
?>
<div class="ff-view-intro"><div class="ff-kicker">ADMIN RELEASE GATE</div><h2>Phê duyệt & đưa lên Web mẫu</h2><p>Chỉ những dự án, cấu hình hoặc phiên bản đã được Tester xác nhận đạt mới có thể phát hành. Tất cả thao tác phê duyệt được lưu vào nhật ký.</p></div>
<div class="workflow-steps mb-4"><span><b>1</b> Dev gửi</span><i class="bi bi-arrow-right"></i><span><b>2</b> Tester đạt</span><i class="bi bi-arrow-right"></i><span class="is-current"><b>3</b> Admin duyệt</span><i class="bi bi-arrow-right"></i><span><b>4</b> Web mẫu</span></div>
<div class="alert alert-primary">Hiện có <strong><?= $ready ?></strong> yêu cầu đã vượt qua Tester và đang chờ quyết định phát hành.</div>
<section class="panel table-panel"><div class="table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Loại / Hạng mục</th><th>Người gửi</th><th>Kết quả Tester</th><th>Trạng thái</th><th>Quyết định Admin</th></tr></thead><tbody>
<?php foreach($items as $item): $r=$item['record'];$type=$item['type'];$status=ReviewWorkflow::normalize($r['status']); ?>
<tr><td><strong><?= h($item['name']) ?></strong><small><?= h(name_of($db['projects'],$item['project'])) ?> · <?= h($item['detail']) ?></small><small><?= h($r['reason']??'') ?></small></td>
<td><?= h(name_of($db['users'],(int)$r['requested_by'])) ?></td><td><?= !empty($r['tester_by'])?h(name_of($db['users'],(int)$r['tester_by'])):'—' ?><small><?= h($r['tester_note']??'Chưa kiểm thử') ?></small></td>
<td><?= pill(ReviewWorkflow::label($status),ReviewWorkflow::color($status)) ?></td>
<td><?php if($status==='tester_approved'): ?>
<?php if($type==='feature'): ?>
<form method="post" action="/" class="workflow-admin-form"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="back" value="approvals"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
<?php else: ?>
<form method="post" action="/" class="workflow-admin-form"><?= csrf_field() ?><input type="hidden" name="action" value="workflow_admin"><input type="hidden" name="type" value="<?= h($type) ?>"><input type="hidden" name="back" value="approvals"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
<?php endif; ?>
<input class="form-control form-control-sm mb-2" name="note" placeholder="Lý do phê duyệt (ít nhất 5 ký tự)" required minlength="5" maxlength="500">
<div class="d-flex flex-wrap gap-2"><button class="btn primary small" name="decision" value="approved">Phát hành</button><button class="btn danger small" name="decision" value="rejected">Từ chối</button></div></form>
<?php else: ?><span class="text-secondary small"><?= $status==='pending_test'?'Đang chờ Tester':($status==='tester_rejected'?'Trả về Developer':'Đã xử lý') ?></span><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if(!$items): ?><tr><td colspan="5" class="empty">Chưa có yêu cầu nào.</td></tr><?php endif; ?>
</tbody></table></div></section>
