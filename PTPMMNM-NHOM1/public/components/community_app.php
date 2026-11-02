<?php
declare(strict_types=1);
// Included by public/index.php after authentication and page-level permission checks.
$actor = user();
$env = (string)($_GET['env']??'beta');
if (!in_array($env,Policy::ENVS,true)) $env='beta';
if(($actor['role']??'')==='tester') $env='beta';
$projects = array_values(array_filter($db['projects']??[],static fn($p)=>ProjectAccess::canView($db,$actor,(int)$p['id']) && ReviewWorkflow::projectIsPublished($p)));
$projectId=(int)($_GET['project']??($projects[0]['id']??0));
$project=null;
foreach ($projects as $candidate) if((int)$candidate['id']===$projectId){$project=$candidate;break;}
if (!$project) {
    echo '<div class="alert alert-warning">Bạn chưa được cấp quyền xem dự án CommunityPass.</div>';
    return;
}
$flags=[];
foreach(['new_registration','smart_waitlist','dark_theme','push_notification','advanced_search','smart_filter','new_dashboard'] as $key) {
    $flags[$key]=CommunityService::enabled($db,$projectId,$env,$key,$actor);
}
$preferences=$db['community_preferences'][(string)$actor['id']]??[];
$dark=!empty($preferences['dark']) && $flags['dark_theme'];
$q=trim((string)($_GET['q']??''));$q=mb_substr($q,0,100);
$cat=(string)($_GET['category']??'all');
if(!in_array($cat,['all','technology','community','workshop','other'],true))$cat='all';
$communityFormatDate=static function(string $date):string {
    try { return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i'); }
    catch (Throwable) { return $date; }
};
$catNames=['technology'=>'Công nghệ','community'=>'Cộng đồng','workshop'=>'Workshop','other'=>'Khác'];
$events=array_values(array_filter($db['community_events']??[],static fn($e)=>(int)$e['project_id']===$projectId));
usort($events,static fn($a,$b)=>strcmp($a['starts_at'],$b['starts_at']));
$filtered=[];
foreach($events as $event){
    if($flags['smart_filter'] && $cat!=='all' && $event['category']!==$cat)continue;
    if($q!==''){
        $haystack=$event['name'];
        if($flags['advanced_search']) $haystack.=' '.($event['description']??'').' '.($event['location']??'').' '.($catNames[$event['category']]??'');
        $haystack=function_exists('mb_strtolower')?mb_strtolower($haystack,'UTF-8'):strtolower($haystack);
        $needle=function_exists('mb_strtolower')?mb_strtolower($q,'UTF-8'):strtolower($q);
        if(!str_contains($haystack,$needle))continue;
    }
    $filtered[]=$event;
}
$mine=array_values(array_filter($db['community_registrations']??[],static fn($r)=>(int)$r['user_id']===(int)$actor['id'] && $r['status']!=='cancelled'));
$notes=array_values(array_filter($db['community_notifications']??[],static fn($n)=>(int)$n['user_id']===(int)$actor['id']));
usort($notes,static fn($a,$b)=>$b['id']<=>$a['id']);
$confirmedCount=count(array_filter($mine,static fn($r)=>$r['status']==='confirmed'));
$waitingCount=count(array_filter($mine,static fn($r)=>$r['status']==='waitlisted'));
$canManage=($actor['role']??'')==='developer' && ProjectAccess::canEdit($db,$actor,$projectId);
?>
<section class="community-app <?= $dark?'community-dark':'' ?>" id="community-app">
    <div class="community-hero rounded-4 p-4 p-md-5 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-uppercase small fw-semibold community-eyebrow mb-2">CommunityPass / Ứng dụng hoạt động thực tế</div>
                <h2 class="fw-bold mb-2">Sự kiện dành cho bạn</h2>
                <p class="mb-0">Xem sự kiện, đăng ký tham dự, theo dõi danh sách chờ và nhận thông báo ngay trên website.</p>
            </div>
            <span class="community-env-badge"><?= h(strtoupper($env)) ?></span>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="community-stat"><i class="bi bi-calendar3"></i><span>Sự kiện</span><strong><?=count($events)?></strong></div></div>
        <div class="col-6 col-lg-3"><div class="community-stat"><i class="bi bi-check-circle"></i><span>Đã xác nhận</span><strong><?=$confirmedCount?></strong></div></div>
        <div class="col-6 col-lg-3"><div class="community-stat"><i class="bi bi-hourglass-split"></i><span>Đang chờ</span><strong><?=$waitingCount?></strong></div></div>
        <div class="col-6 col-lg-3"><div class="community-stat"><i class="bi bi-bell"></i><span>Thông báo</span><strong><?=count($notes)?></strong></div></div>
    </div>
    <div class="card rounded-4 border-0 shadow-sm mb-4"><div class="card-body p-3 p-md-4">
        <form method="get" action="/" class="row g-3 align-items-end">
            <input type="hidden" name="p" value="community">
            <input type="hidden" name="project" value="<?=$projectId?>">
            <input type="hidden" name="env" value="<?=h($env)?>">
            <div class="col-md-<?= $flags['smart_filter']?'8':'10' ?>">
                <label for="community-q" class="form-label fw-semibold">Tìm sự kiện</label>
                <input class="form-control form-control-lg" type="search" id="community-q" name="q" value="<?=h($q)?>" maxlength="100" placeholder="Tên sự kiện<?= $flags['advanced_search']?', nội dung hoặc địa điểm':'' ?>...">
            </div>
            <?php if($flags['smart_filter']): ?>
            <div class="col-md-2"><label for="community-category" class="form-label fw-semibold">Danh mục</label><select id="community-category" class="form-select form-select-lg" name="category"><option value="all">Tất cả</option>
                <?php foreach($catNames as $key=>$name): ?><option value="<?=h($key)?>" <?=$cat===$key?'selected':''?>><?=h($name)?></option><?php endforeach; ?>
            </select></div>
            <?php endif; ?>
            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-search"></i> Tìm</button></div>
        </form>
        <div class="mt-3 d-flex flex-wrap gap-2">
            <span class="badge rounded-pill text-bg-light border">Tìm thấy <?=count($filtered)?> sự kiện</span>
            <span class="badge rounded-pill <?= $flags['new_registration']?'text-bg-success':'text-bg-secondary' ?>">Đăng ký: <?= $flags['new_registration']?'Đang mở':'Chưa được cấp' ?></span>
            <span class="badge rounded-pill <?= $flags['smart_waitlist']?'text-bg-info':'text-bg-light' ?>">Danh sách chờ: <?= $flags['smart_waitlist']?'Đang mở':'Đang tắt' ?></span>
        </div>
    </div></div>
    <?php if($flags['dark_theme']): ?>
    <form method="post" action="/" class="mb-4">
        <?=csrf_field()?><input type="hidden" name="action" value="community_preference"><input type="hidden" name="dark" value="<?=$dark?'0':'1'?>">
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-moon-stars"></i> <?=$dark?'Tắt':'Bật'?> giao diện tối</button>
    </form>
    <?php endif; ?>
    <?php if($canManage): ?>
    <details class="community-create mb-4 card rounded-4 border-0 shadow-sm"><summary class="p-4 fw-semibold"><i class="bi bi-plus-circle me-2"></i> Developer: Tạo sự kiện mới <i class="bi bi-chevron-down float-end"></i></summary>
      <div class="card-body border-top"><form method="post" action="/" class="row g-3">
       <?=csrf_field()?><input type="hidden" name="action" value="community_event_create"><input type="hidden" name="project_id" value="<?=$projectId?>">
       <div class="col-md-8"><label class="form-label">Tên sự kiện</label><input class="form-control" name="name" minlength="5" maxlength="120" required></div>
       <div class="col-md-4"><label class="form-label">Danh mục</label><select name="category" class="form-select"><option value="technology">Công nghệ</option><option value="community">Cộng đồng</option><option value="workshop">Workshop</option><option value="other">Khác</option></select></div>
       <div class="col-md-5"><label class="form-label">Địa điểm</label><input class="form-control" name="location" minlength="3" maxlength="120" required></div>
       <div class="col-md-4"><label class="form-label">Thời gian bắt đầu</label><input class="form-control" type="datetime-local" name="starts_at" required></div>
       <div class="col-md-3"><label class="form-label">Sức chứa</label><input class="form-control" type="number" name="capacity" min="1" max="5000" value="30" required></div>
       <div class="col-12"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="3" maxlength="600"></textarea></div>
       <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Tạo sự kiện</button></div>
      </form></div>
    </details>
    <?php endif; ?>
    <div class="d-flex align-items-center justify-content-between mb-3"><h3 class="fs-5 fw-bold mb-0">Sự kiện <?= $q!==''?'phù hợp':'sắp diễn ra' ?></h3><span class="text-secondary small"><?=count($filtered)?> kết quả</span></div>
    <div class="row g-3 mb-4" id="community-events">
    <?php foreach($filtered as $event):
        $stats=CommunityService::status($db,(int)$event['id']);
        $myReg=CommunityService::registration($db,(int)$event['id'],(int)$actor['id']);
        $left=max(0,(int)$event['capacity']-$stats['confirmed']);
        $past=strtotime($event['starts_at'])<time();
        $full=$left<=0;
    ?>
      <div class="col-md-6 col-xl-4"><article class="card h-100 community-event shadow-sm border-0 rounded-4" id="event-<?= (int)$event['id'] ?>">
       <div class="card-body p-4 d-flex flex-column">
        <div class="d-flex justify-content-between align-items-center mb-3"><span class="badge text-bg-light border"><?=h($catNames[$event['category']]??'Khác')?></span>
        <span class="badge <?= $past?'text-bg-secondary':($full?'text-bg-warning':'text-bg-success') ?>"><?= $past?'Đã diễn ra':($full?'Hết chỗ':'Còn '.$left.' chỗ') ?></span></div>
        <h4 class="fs-5 fw-bold mb-2"><?=h($event['name'])?></h4>
        <p class="small text-secondary mb-3"><?=h($event['description'])?></p>
        <div class="community-event-meta mt-auto"><div><i class="bi bi-calendar-event"></i> <?=h($communityFormatDate($event['starts_at']))?></div><div><i class="bi bi-geo-alt"></i> <?=h($event['location'])?></div><div><i class="bi bi-people"></i> <?=$stats['confirmed']?> / <?=(int)$event['capacity']?> xác nhận · <?=$stats['waitlisted']?> chờ</div></div>
        <?php if(($actor['role']??'')==='tester'): ?>
          <?php if($myReg): ?>
          <div class="alert <?=$myReg['status']==='confirmed'?'alert-success':'alert-info'?> py-2 mt-3 mb-2 small"><?= $myReg['status']==='confirmed'?'Bạn đã đăng ký thành công':'Bạn đang trong danh sách chờ' ?></div>
          <form method="post" action="/" class="mt-1"><?=csrf_field()?><input type="hidden" name="action" value="community_cancel"><input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><button class="btn btn-outline-danger w-100">Hủy đăng ký</button></form>
          <?php elseif(!$past && $flags['new_registration'] && (!$full || $flags['smart_waitlist'])): ?>
          <form method="post" action="/" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="community_register"><input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input type="hidden" name="environment" value="<?=h($env)?>"><button class="btn <?= $full?'btn-outline-primary':'btn-primary' ?> w-100"><?= $full?'Tham gia danh sách chờ':'Đăng ký tham dự' ?></button></form>
          <?php else: ?><button class="btn btn-light border w-100 mt-3" disabled><?= $past?'Sự kiện đã diễn ra':($flags['new_registration']?'Hiện chưa nhận đăng ký':'Chưa được cấp tính năng đăng ký') ?></button><?php endif; ?>
        <?php else: ?><a class="btn btn-outline-primary mt-3" href="/?p=versions">Quản lý cấu hình phát hành</a><?php endif; ?>
       </div></article></div>
    <?php endforeach; ?>
    <?php if(!$filtered): ?><div class="col-12"><div class="community-empty p-5 text-center rounded-4"><i class="bi bi-calendar-x fs-1 text-secondary"></i><h4 class="mt-3">Chưa tìm thấy sự kiện</h4><p class="text-secondary">Hãy thử từ khóa khác hoặc nhờ Developer tạo sự kiện mới.</p></div></div><?php endif; ?>
    </div>
    <?php if($flags['push_notification'] && $notes): ?>
    <section class="card border-0 rounded-4 shadow-sm mb-4" id="community-notifications"><div class="card-body p-4"><h3 class="fs-5 fw-bold mb-3"><i class="bi bi-bell me-2"></i>Thông báo trong ứng dụng</h3>
    <?php foreach(array_slice($notes,0,12) as $n): ?>
     <div class="community-note d-flex gap-3 align-items-start border-bottom py-3"><i class="bi bi-info-circle text-primary"></i><div class="flex-grow-1"><strong class="d-block"><?=h($n['message'])?></strong><small class="text-secondary"><?=h($communityFormatDate($n['created_at']))?></small></div>
     <?php if(empty($n['read_at'])): ?><form method="post" action="/"><?=csrf_field()?><input type="hidden" name="action" value="community_notify_read"><input type="hidden" name="notification_id" value="<?=(int)$n['id']?>"><button class="btn btn-sm btn-outline-secondary">Đã đọc</button></form><?php endif; ?></div>
    <?php endforeach; ?></div></section>
    <?php endif; ?>
    <?php if($flags['new_dashboard'] && $mine): ?>
    <section class="card border-0 rounded-4 shadow-sm mb-4" id="community-my-events"><div class="card-body p-4"><h3 class="fs-5 fw-bold mb-3">Lịch đăng ký của tôi</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sự kiện</th><th>Trạng thái</th><th>Thời điểm đăng ký</th></tr></thead><tbody>
    <?php foreach($mine as $r): $e=CommunityService::event($db,(int)$r['event_id']); ?>
    <tr><td><?=h($e['name'])?></td><td><span class="badge <?= $r['status']==='confirmed'?'text-bg-success':'text-bg-info' ?>"><?= $r['status']==='confirmed'?'Đã xác nhận':'Đang chờ' ?></span></td><td><?=h($communityFormatDate($r['created_at']))?></td></tr>
    <?php endforeach; ?></tbody></table></div></div></section>
    <?php endif; ?>
    <div class="small text-secondary"><i class="bi bi-shield-lock me-1"></i>Thông tin đăng ký được lưu trong dữ liệu của dự án; quyết định bật/tắt luôn được kiểm tra lại trên máy chủ theo phiên bản đã phát hành.</div>
</section>
