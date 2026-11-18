<?php
/** FlagFlow Enterprise dashboards - Admin / Developer / Tester, dữ liệu thực từ Store. */
declare(strict_types=1);
$role=(string)(user()['role']??'tester');
$uid=(int)(user()['id']??0);
$projects=array_values(array_filter($db['projects']??[],static fn($p)=>ProjectAccess::canView($db,user(),(int)$p['id'])));
$projectIds=array_fill_keys(array_map(static fn($p)=>(int)$p['id'],$projects),true);
$features=array_values(array_filter($db['features']??[],static fn($f)=>isset($projectIds[(int)($f['project_id']??0)])));
$featureIds=array_fill_keys(array_map(static fn($f)=>(int)$f['id'],$features),true);
$users=$db['users']??[];
$projectById=[];foreach($projects as $p){$projectById[(int)$p['id']]=$p;}
$featureById=[];foreach($features as $f){$featureById[(int)$f['id']]=$f;}
$visibleRequests=[];
foreach($db['requests']??[] as $r){
    $f=$featureById[(int)($r['feature_id']??0)]??null;
    if(!$f)continue;
    $visibleRequests[]=['type'=>'feature','item'=>$r,'project_id'=>(int)$f['project_id'],'name'=>(string)$f['name']];
}
foreach($db['release_submissions']??[] as $r){
    $pid=(int)($r['project_id']??0);
    if(!isset($projectIds[$pid]))continue;
    $title=($r['type']??'')==='project'?'Phát hành dự án '.($projectById[$pid]['name']??''):'Phát hành phiên bản #'.(int)($r['target_id']??0);
    $visibleRequests[]=['type'=>'release','item'=>$r,'project_id'=>$pid,'name'=>$title];
}
usort($visibleRequests,static fn($a,$b)=>strcmp((string)($b['item']['created_at']??''),(string)($a['item']['created_at']??'')));
$pendingTests=array_values(array_filter($visibleRequests,static fn($x)=>ReviewWorkflow::normalize((string)($x['item']['status']??''))==='pending_test'));
$pendingAdmin=array_values(array_filter($visibleRequests,static fn($x)=>ReviewWorkflow::normalize((string)($x['item']['status']??''))==='tester_approved'));
$myRequests=array_values(array_filter($visibleRequests,static fn($x)=>(int)($x['item']['requested_by']??0)===$uid));
$myPendingTests=array_values(array_filter($myRequests,static fn($x)=>ReviewWorkflow::normalize((string)($x['item']['status']??''))==='pending_test'));
$completedTests=array_values(array_filter($visibleRequests,static fn($x)=>in_array(ReviewWorkflow::normalize((string)($x['item']['status']??'')),['tester_approved','approved','rejected','tester_rejected'],true)));
$publishedVersions=array_values(array_filter($db['versions']??[],static fn($v)=>isset($projectIds[(int)($v['project_id']??0)])&&($v['status']??'')==='published'));
$draftVersions=array_values(array_filter($db['versions']??[],static fn($v)=>isset($projectIds[(int)($v['project_id']??0)])&&($v['status']??'')==='draft'));
$configs=array_values(array_filter($db['configs']??[],static fn($c)=>isset($featureIds[(int)($c['feature_id']??0)])));
$envNames=['development'=>'Development','beta'=>'Beta','production'=>'Production'];
$envCounts=[];
foreach($envNames as $env=>$label){
    $matching=array_values(array_filter($configs,static fn($c)=>($c['environment']??'')===$env));
    $envCounts[$env]=['enabled'=>count(array_filter($matching,static fn($c)=>!empty($c['enabled']))),'total'=>count($matching)];
}
$activity=$role==='admin' ? array_slice(array_reverse($db['audit']??[]),0,5) : [];
if(!$activity){
    $visibleHistory=array_values(array_filter($db['release_history']??[],
        static fn($log)=>isset($projectIds[(int)($log['project_id']??0)])
    ));
    foreach(array_slice(array_reverse($visibleHistory),0,5) as $log){
        $pid=(int)($log['project_id']??0);
        $activity[]=['action'=>'Phiên bản '.(string)($log['action']??'phát hành'),
          'detail'=>'Dự án '.(string)($projectById[$pid]['name']??'#'.$pid),
          'at'=>$log['at']??''];
    }
}
$roleHead=match($role){'admin'=>['Bảng điều khiển quản trị','Tổng quan hoạt động, phê duyệt yêu cầu và trạng thái phát hành trên toàn hệ thống.','bi-shield-check'],
 'developer'=>['Không gian làm việc của Developer','Theo dõi dự án, tiến độ tính năng, bản nháp và yêu cầu đã gửi.','bi-braces'],
 default=>['Trung tâm kiểm thử','Tiếp nhận yêu cầu từ Developer, kiểm tra và xác nhận chất lượng trước khi phát hành.','bi-clipboard-check']};
$cards=match($role){
 'admin'=>[
 ['Số dự án',count($projects),'projects','bi-folder2-open','blue','Dự án bạn có quyền quản lý'],
 ['Chờ Admin duyệt',count($pendingAdmin),'approvals','bi-shield-check','amber','Đã được Tester xác nhận'],
 ['Phiên bản đã phát hành',count($publishedVersions),'versions','bi-layers','violet','Trong dự án bạn có quyền xem'],
 ['Tài khoản hệ thống',count($users),'accounts','bi-people','green','Admin, Developer và Tester']],
 'developer'=>[
 ['Tính năng của dự án',count($features),'features','bi-toggles','blue','Tính năng của dự án được cấp'],
 ['Chờ Tester kiểm thử',count($myPendingTests),'my_requests','bi-hourglass-split','amber','Yêu cầu do bạn gửi'],
 ['Bản nháp cấu hình',count($draftVersions),'versions','bi-layers','violet','Có thể tiếp tục chỉnh sửa'],
 ['Yêu cầu đã gửi',count($myRequests),'my_requests','bi-send','green','Lịch sử đề xuất của bạn']],
 default=>[
 ['Chờ kiểm thử',count($pendingTests),'test_queue','bi-clipboard-check','blue','Cần thực hiện kiểm thử'],
 ['Đã có kết quả',count($completedTests),'test_queue','bi-shield-check','green','Yêu cầu đã được xử lý'],
 ['Tính năng có thể xem',count($features),'my_features','bi-stars','violet','Theo quyền dự án'],
 ['Dự án được cấp',count($projects),'preview','bi-folder2-open','amber','Không gian thử nghiệm được cấp']]
};
$mainRows=$role==='admin'?$pendingAdmin:($role==='developer'?$myRequests:$pendingTests);
$rowsTitle=match($role){'admin'=>'Yêu cầu chờ phê duyệt','developer'=>'Pipeline tính năng của tôi',default=>'Hàng chờ kiểm thử'};
$rowsSubtitle=match($role){'admin'=>'Các yêu cầu đã vượt qua bước kiểm thử và cần Admin quyết định.','developer'=>'Theo dõi trạng thái các thay đổi đã gửi và hành động tiếp theo.',default=>'Yêu cầu thực tế đang chờ bạn kiểm thử và xác nhận.'};
$rowsUrl=match($role){'admin'=>'approvals','developer'=>'my_requests',default=>'test_queue'};
$quickLink=match($role){'admin'=>['approvals','Mở phê duyệt'], 'developer'=>['submit','Gửi tính năng mới'],default=>['test_queue','Bắt đầu kiểm thử']};
$namesById=[];foreach($users as $u){$namesById[(int)($u['id']??0)]=(string)($u['name']??$u['username']??'Thành viên');}
?>
<div class="e-dash e-role-<?= h($role) ?>">
  <header class="e-dash-hero">
    <div class="e-dash-hero-title"><div class="e-heading-icon"><i class="bi <?= h($roleHead[2]) ?>"></i></div><div>
      <div class="e-eyebrow">FLAGFLOW / <?= strtoupper(h($role)) ?> WORKSPACE</div>
      <h1><?= h($roleHead[0]) ?></h1><p><?= h($roleHead[1]) ?></p>
    </div></div>
    <a class="btn btn-primary e-hero-button" href="/?p=<?= h($quickLink[0]) ?>"><i class="bi bi-arrow-up-right"></i> <?= h($quickLink[1]) ?></a>
  </header>
  <section class="e-metrics" aria-label="Tổng quan số liệu">
    <?php foreach($cards as $card): ?>
    <a class="e-metric e-tone-<?= h($card[4]) ?>" href="/?p=<?= h($card[2]) ?>">
      <div class="e-metric-top"><span class="e-metric-icon"><i class="bi <?= h($card[3]) ?>"></i></span><span class="e-metric-arrow"><i class="bi bi-arrow-up-right"></i></span></div>
      <span class="e-metric-label"><?= h($card[0]) ?></span><strong><?= (int)$card[1] ?></strong><small><?= h($card[5]) ?></small>
    </a>
    <?php endforeach; ?>
  </section>

  <div class="e-dashboard-grid">
    <section class="e-panel e-queue-panel">
      <header class="e-panel-header"><div><div class="e-eyebrow">THEO DÕI CÔNG VIỆC</div><h2><?= h($rowsTitle) ?></h2><p><?= h($rowsSubtitle) ?></p></div><a class="e-text-link" href="/?p=<?= h($rowsUrl) ?>">Xem tất cả <i class="bi bi-arrow-right"></i></a></header>
      <div class="e-table-scroll"><table class="e-table"><thead><tr><th>Nội dung</th><th>Dự án</th><th>Người gửi</th><th>Trạng thái</th><th></th></tr></thead><tbody>
      <?php foreach(array_slice($mainRows,0,7) as $entry): $r=$entry['item'];$status=ReviewWorkflow::normalize((string)($r['status']??'')); $statusLabel=ReviewWorkflow::label($status); $statusTone=in_array($status,['approved','tester_approved'],true)?'green':(in_array($status,['rejected','tester_rejected'],true)?'red':($status==='pending_test'?'blue':'amber')); $link='/?p='.$rowsUrl; ?>
        <tr><td><div class="e-table-subject"><span class="e-row-icon"><i class="bi <?= $entry['type']==='feature'?'bi-sliders':'bi-layers' ?>"></i></span><span><b><?= h($entry['name']) ?></b><small>Yêu cầu #<?= (int)($r['id']??0) ?></small></span></div></td><td><span class="e-project-tag"><?= h((string)($projectById[$entry['project_id']]['name']??'Dự án')) ?></span></td><td><?= h($namesById[(int)($r['requested_by']??0)]??'Thành viên') ?></td><td><span class="e-status e-status-<?= $statusTone ?>"><?= h($statusLabel) ?></span></td><td><a class="e-table-action" href="<?= h($link) ?>" aria-label="Mở yêu cầu"><i class="bi bi-arrow-up-right"></i></a></td></tr>
      <?php endforeach; ?>
      <?php if(!$mainRows): ?><tr><td colspan="5"><div class="e-empty"><i class="bi bi-check-circle"></i><b>Chưa có yêu cầu ở trạng thái này.</b><span>Các thay đổi mới sẽ xuất hiện tại đây khi phát sinh.</span></div></td></tr><?php endif; ?>
      </tbody></table></div>
    </section>

    <section class="e-panel e-env-panel"><header class="e-panel-header"><div><div class="e-eyebrow">MÔI TRƯỜNG</div><h2>Trạng thái phát hành</h2><p>Cấu hình bật/tắt theo dữ liệu hiện tại.</p></div><a class="e-text-link" href="/?p=versions"><i class="bi bi-arrow-up-right"></i></a></header>
      <div class="e-env-list">
       <?php foreach($envNames as $env=>$label):$counts=$envCounts[$env];$percent=$counts['total']?round(100*$counts['enabled']/$counts['total']):0; ?>
        <div class="e-env-row"><span class="e-env-icon e-env-<?= h($env) ?>"><i class="bi <?= $env==='development'?'bi-braces':($env==='beta'?'bi-clipboard-check':'bi-layers') ?>"></i></span><div class="e-env-main"><div class="e-env-name"><b><?= h($label) ?></b><small><?= (int)$counts['enabled'] ?> / <?= (int)$counts['total'] ?> đang bật</small></div><div class="e-progress"><span class="e-progress-bar e-env-<?= h($env) ?>" style="width:<?= (int)$percent ?>%"></span></div></div><span class="e-env-percent"><?= (int)$percent ?>%</span></div>
       <?php endforeach; ?>
      </div>
      <div class="e-panel-note">Tỷ lệ dựa trên cấu hình đã lưu, không phải số liệu giả lập.</div>
    </section>
  </div>

  <div class="e-dashboard-grid e-dashboard-grid-lower">
    <section class="e-panel e-projects-panel"><header class="e-panel-header"><div><div class="e-eyebrow">DỰ ÁN</div><h2><?= ($role==='developer'?'Dự án tôi tham gia':($role==='tester'?'Dự án được cấp quyền':'Dự án đang quản lý')) ?></h2><p>Danh sách dự án bạn được phép truy cập.</p></div><a class="e-text-link" href="/?p=projects">Xem dự án <i class="bi bi-arrow-right"></i></a></header>
      <div class="e-project-list">
      <?php foreach(array_slice($projects,0,5) as $p): $featureCount=count(array_filter($features,static fn($f)=>(int)$f['project_id']===(int)$p['id'])); ?>
         <a class="e-project-item" href="/?p=projects"><span class="e-project-symbol"><i class="bi bi-folder2-open"></i></span><span><b><?= h((string)$p['name']) ?></b><small><?= h(mb_substr((string)($p['description']??'Dự án FlagFlow'),0,88)) ?></small></span><span class="e-project-count"><?= $featureCount ?> tính năng</span><i class="bi bi-chevron-right"></i></a>
      <?php endforeach; ?>
      <?php if(!$projects): ?><div class="e-empty"><i class="bi bi-folder2-open"></i><b>Chưa có dự án được cấp quyền</b><span>Liên hệ Admin hoặc tạo dự án mới nếu bạn có quyền.</span></div><?php endif; ?>
      </div>
    </section>
    <section class="e-panel e-activity-panel"><header class="e-panel-header"><div><div class="e-eyebrow">NHẬT KÝ</div><h2>Hoạt động gần đây</h2><p>Những thay đổi có trong hệ thống.</p></div><?php if($role==='admin'):?><a class="e-text-link" href="/?p=audit">Xem tất cả <i class="bi bi-arrow-right"></i></a><?php endif; ?></header>
      <div class="e-activity-list">
      <?php foreach($activity as $i=>$a): ?><div class="e-activity-row"><span class="e-activity-icon"><i class="bi <?= $i%2===0?'bi-check-circle':'bi-layers' ?>"></i></span><span><b><?= h((string)($a['action']??'Cập nhật')) ?></b><small><?= h(mb_substr((string)($a['detail']??''),0,100)) ?></small></span><time><?= h(str_replace('T',' ',substr((string)($a['at']??''),0,16))) ?></time></div><?php endforeach; ?>
      <?php if(!$activity):?><div class="e-empty"><i class="bi bi-clock-history"></i><b>Chưa có hoạt động</b><span>Hoạt động và lịch sử phát hành sẽ xuất hiện tại đây.</span></div><?php endif; ?>
      </div>
    </section>
  </div>
  <?php if($role==='developer'): ?><div class="e-dashboard-cta"><span class="e-cta-icon"><i class="bi bi-send"></i></span><div><b>Bạn có tính năng mới cần gửi kiểm thử?</b><small>Tạo yêu cầu từ trang Gửi tính năng mới, sau đó theo dõi phản hồi trong Yêu cầu đã gửi.</small></div><a class="btn btn-primary" href="/?p=submit">Gửi đề xuất <i class="bi bi-arrow-right"></i></a></div><?php endif; ?>
  <?php if($role==='tester'): ?><div class="e-dashboard-cta e-cta-tester"><span class="e-cta-icon"><i class="bi bi-clipboard-check"></i></span><div><b>Kiểm thử và ghi nhận kết quả</b><small>Truy cập hàng chờ để thực hiện checklist, mô phỏng và trả kết quả về Developer hoặc chuyển Admin.</small></div><a class="btn btn-primary" href="/?p=test_queue">Mở hàng chờ <i class="bi bi-arrow-right"></i></a></div><?php endif; ?>
</div>
