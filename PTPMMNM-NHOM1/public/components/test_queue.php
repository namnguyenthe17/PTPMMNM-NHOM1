<?php
/** Tester-only review portal. All actions are validated again in ReviewWorkflow. */
declare(strict_types=1);
if((user()['role']??'')!=='tester'){http_response_code(403);echo 'Không có quyền kiểm thử';return;}
$items=[];
foreach($db['requests']??[] as $r){
    $f=Policy::find($db['features'],(int)$r['feature_id']);
    if(!$f||!ProjectAccess::canView($db,user(),(int)$f['project_id']))continue;
    $items[]=['type'=>'feature','record'=>$r,'project_id'=>(int)$f['project_id'],'title'=>$f['name'],'details'=>'Cấu hình '.strtoupper($r['environment']).' · '.($r['enabled']?'Bật':'Tắt'),'feature'=>$f];
}
foreach($db['release_submissions']??[] as $r){
    if(!ProjectAccess::canView($db,user(),(int)$r['project_id']))continue;
    $name=$r['type']==='project'?name_of($db['projects'],(int)$r['target_id']):'Phiên bản #'.(int)$r['target_id'];
    $items[]=['type'=>$r['type'],'record'=>$r,'project_id'=>(int)$r['project_id'],'title'=>$name,'details'=>$r['type']==='project'?'Dự án mới · Chưa lên Web mẫu':'Bản nháp · '.strtoupper($r['environment'])];
}
usort($items,static fn($a,$b)=>strcmp((string)($b['record']['created_at']??''),(string)($a['record']['created_at']??'')));
$pending=count(array_filter($items,static fn($i)=>ReviewWorkflow::normalize($i['record']['status'])==='pending_test'));
$done=count(array_filter($items,static fn($i)=>in_array(ReviewWorkflow::normalize($i['record']['status']),['tester_approved','approved'],true)));
?>
<div class="ff-view-intro"><div class="ff-kicker">TESTER QUALITY GATE</div><h2>Tiếp nhận và kiểm thử từ Developer</h2>
<p>Kiểm tra bản thử nghiệm trước khi xác nhận đạt. Khi đạt, hệ thống mới chuyển yêu cầu tới Admin; khi không đạt, ghi lỗi để Developer sửa lại. Bản đang kiểm thử không được tự động phát hành lên Web mẫu.</p></div>
<div class="row g-3 mb-4"><div class="col-md-4"><div class="ff-kpi"><span>Chờ kiểm thử</span><strong><?= $pending ?></strong><i class="bi bi-hourglass-split"></i></div></div>
<div class="col-md-4"><div class="ff-kpi"><span>Đã được Tester xác nhận đạt</span><strong><?= $done ?></strong><i class="bi bi-clipboard-check"></i></div></div>
<div class="col-md-4"><div class="ff-kpi"><span>Yêu cầu có thể xem</span><strong><?= count($items) ?></strong><i class="bi bi-list-check"></i></div></div></div>
<div class="workflow-steps mb-4"><span><b>1</b> Dev gửi</span><i class="bi bi-arrow-right"></i><span class="is-current"><b>2</b> Tester kiểm thử</span><i class="bi bi-arrow-right"></i><span><b>3</b> Admin phát hành</span><i class="bi bi-arrow-right"></i><span><b>4</b> Web mẫu</span></div>
<?php if(!$items): ?><section class="panel"><div class="text-center py-4 text-secondary"><i class="bi bi-inbox fs-1"></i><p class="mt-3">Chưa có yêu cầu kiểm thử từ Developer.</p></div></section><?php endif; ?>
<?php foreach($items as $item): ?>
<?php
$r=$item['record'];$type=$item['type'];$status=ReviewWorkflow::normalize($r['status']);
$isPending=$status==='pending_test';
$rid=(int)$r['id'];
?>
<section class="panel workflow-review-card" id="test-<?= $type ?>-<?= $rid ?>">
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3"><div>
<div class="ff-kicker"><?= $type==='project'?'DỰ ÁN':($type==='version'?'PHIÊN BẢN':'CẤU HÌNH TÍNH NĂNG') ?> · #<?= $rid ?></div>
<h3 class="mt-2 mb-1"><?= h($item['title']) ?></h3><p class="text-secondary small mb-0"><?= h(name_of($db['projects'],$item['project_id'])) ?> · <?= h($item['details']) ?></p></div>
<?= pill(ReviewWorkflow::label($status),ReviewWorkflow::color($status)) ?></div>
<div class="workflow-info-grid mb-3"><div><small>Developer gửi</small><b><?= h(name_of($db['users'],(int)$r['requested_by'])) ?></b></div>
<div><small>Nội dung cần kiểm thử</small><b><?= h($r['reason']??'') ?></b></div>
<div><small>Ngày gửi</small><b><?= h(substr((string)($r['created_at']??''),0,16)) ?></b></div></div>
<?php if($type==='feature'):
    $f=$item['feature'];
    $active=VersionService::active($db,(int)$f['project_id'],(string)$r['environment']);
    $currentRule=null;
    foreach($active['flags'] as $rule)if((int)$rule['feature_id']===(int)$f['id']){$currentRule=$rule;break;}
    $candidate=$active;
    $newRule=$currentRule??['feature_id'=>(int)$f['id'],'enabled'=>false,'percentage'=>100,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]];
    $newRule['enabled']=(bool)$r['enabled'];$newRule['group_id']=$r['group_id'];
    $replaced=false;
    foreach($candidate['flags'] as &$rule)if((int)$rule['feature_id']===(int)$f['id']){$rule=$newRule;$replaced=true;break;}
    unset($rule);
    if(!$replaced)$candidate['flags'][]=$newRule;
    $sampleUser=(string)($_GET['sample_user']??'user0001');
    if(!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$sampleUser))$sampleUser='user0001';
    $before=FlagEngine::evaluate($db,$active,(int)$f['id'],$sampleUser);
    $after=FlagEngine::evaluate($db,$candidate,(int)$f['id'],$sampleUser);
?>
<div class="workflow-lab"><h4><i class="bi bi-beaker"></i> Kiểm tra mô phỏng trước khi phát hành</h4>
<p class="small text-secondary mb-3">Dùng mã người dùng thử để xem khác biệt. Mô phỏng này <b>không ghi đè</b> phiên bản đã phát hành.</p>
<form method="get" class="row g-2 align-items-end mb-3"><input type="hidden" name="p" value="test_queue"><div class="col-md-7"><label class="form-label">Mã người dùng kiểm tra</label><input class="form-control" name="sample_user" maxlength="64" value="<?= h($sampleUser) ?>" required></div><div class="col-md-5"><button class="btn secondary w-100">Mô phỏng kết quả</button></div></form>
<div class="row g-2"><div class="col-sm-6"><div class="workflow-result"><small>Trước thay đổi</small><strong><?= $before['enabled']?'ĐANG BẬT':'ĐANG TẮT' ?></strong><small><?= h($before['reason']) ?></small></div></div><div class="col-sm-6"><div class="workflow-result is-new"><small>Đề xuất của Dev (chưa phát hành)</small><strong><?= $after['enabled']?'SẼ BẬT':'SẼ TẮT' ?></strong><small><?= h($after['reason']) ?></small></div></div></div></div>
<?php elseif($type==='version'):
  $version=VersionService::get($db,(int)$r['target_id']);
?>
<div class="workflow-lab"><h4><i class="bi bi-layers"></i> Bản nháp chờ thử nghiệm</h4><p>Phiên bản v<?= (int)$version['number'] ?> · <?= h(strtoupper($version['environment'])) ?> · <?= count($version['flags']) ?> cờ tính năng. Bản đang test là bản nháp, chưa thay thế bản đã phát hành.</p>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tính năng</th><th>Trạng thái dự kiến</th><th>Tỷ lệ</th></tr></thead><tbody>
<?php foreach($version['flags'] as $flag): ?><tr><td><?= h(name_of($db['features'],(int)$flag['feature_id'])) ?></td><td><?= $flag['enabled']?'Bật':'Tắt' ?></td><td><?= (int)$flag['percentage'] ?>%</td></tr><?php endforeach; ?></tbody></table></div></div>
<?php else:
  $project=Policy::find($db['projects'],(int)$r['target_id']);
  $pf=array_values(array_filter($db['features'],static fn($f)=>(int)$f['project_id']===(int)$project['id']));
?>
<div class="workflow-lab"><h4><i class="bi bi-folder-check"></i> Nội dung dự án trước khi lên Web mẫu</h4><p><?= h($project['description']??'') ?></p><div class="d-flex flex-wrap gap-2">
<?php foreach($pf as $flag): ?><span class="badge text-bg-light border p-2"><?= h($flag['name']) ?></span><?php endforeach; ?>
<?php if(!$pf): ?><p class="text-secondary small">Dự án chưa có tính năng. Hãy kiểm tra thông tin dự án và quyền truy cập.</p><?php endif; ?></div></div>
<?php endif; ?>
<?php if($isPending): ?>
<form method="post" action="/" class="workflow-test-form mt-3">
<?= csrf_field() ?><input type="hidden" name="action" value="workflow_test"><input type="hidden" name="back" value="test_queue"><input type="hidden" name="type" value="<?= h($type) ?>"><input type="hidden" name="request_id" value="<?= $rid ?>">
<h4 class="mb-2">Checklist kiểm thử</h4><p class="small text-secondary">Đánh dấu đủ cả ba mục để xác nhận đạt; nếu không đạt, ghi rõ lỗi rồi chọn Trả về Developer.</p>
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="checks[functional]" value="1" id="functional-<?= $type.$rid ?>"><label class="form-check-label" for="functional-<?= $type.$rid ?>">Đã kiểm tra thao tác chính và kết quả mong đợi</label></div>
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="checks[permissions]" value="1" id="permissions-<?= $type.$rid ?>"><label class="form-check-label" for="permissions-<?= $type.$rid ?>">Đã kiểm tra phân quyền và tài khoản được phép sử dụng</label></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="checks[errors]" value="1" id="errors-<?= $type.$rid ?>"><label class="form-check-label" for="errors-<?= $type.$rid ?>">Đã kiểm tra các trường hợp lỗi hoặc không có dữ liệu</label></div>
<label class="d-block mb-2">Báo cáo kiểm thử / lỗi phát hiện<textarea name="note" class="form-control" minlength="5" maxlength="500" required rows="3" placeholder="Ví dụ: Đã test mã user0001, kết quả đúng; không có lỗi... hoặc mô tả lỗi để Dev sửa"></textarea></label>
<div class="d-flex gap-2 flex-wrap"><button class="btn primary" name="decision" value="tester_approved"><i class="bi bi-check2-circle"></i> Kiểm thử đạt · Gửi Admin</button><button class="btn danger" name="decision" value="tester_rejected"><i class="bi bi-arrow-return-left"></i> Không đạt · Trả về Dev</button></div>
</form>
<?php else: ?><div class="alert alert-light border mt-3 mb-0 small"><b>Kết quả Tester:</b> <?= h($r['tester_note']??'Chưa có') ?> · <?= h($r['tested_at']??'') ?><?php if(!empty($r['review_note'])): ?><div><b>Admin:</b> <?= h($r['review_note']) ?></div><?php endif; ?></div><?php endif; ?>
</section>
<?php endforeach; ?>
