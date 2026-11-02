<?php
declare(strict_types=1);
/** Six isolated, server-gated feature experiences for CommunityPass. */
$actor = user();
$slug = (string)($_GET['feature'] ?? 'register');
$catalog = [
    'register' => ['flag'=>'new_registration','title'=>'Đăng ký sự kiện','icon'=>'calendar-event','subtitle'=>'Chọn sự kiện và đặt chỗ. Đăng ký được lưu vào hệ thống.'],
    'waitlist' => ['flag'=>'smart_waitlist','title'=>'Danh sách chờ thông minh','icon'=>'people','subtitle'=>'Theo dõi vị trí đang chờ và tiến trình xác nhận chỗ.'],
    'dark' => ['flag'=>'dark_theme','title'=>'Giao diện tối','icon'=>'moon-stars','subtitle'=>'Chuyển giao diện sáng / tối và lưu sở thích cho tài khoản.'],
    'search' => ['flag'=>'advanced_search','title'=>'Tìm kiếm nâng cao','icon'=>'search','subtitle'=>'Tìm sự kiện theo tên, địa điểm, mô tả và lọc theo danh mục.'],
    'notifications' => ['flag'=>'push_notification','title'=>'Trung tâm thông báo','icon'=>'bell','subtitle'=>'Theo dõi thông báo đăng ký, danh sách chờ và tình trạng đã đọc.'],
    'profile' => ['flag'=>'edit_profile','title'=>'Sửa thông tin cá nhân','icon'=>'person-vcard','subtitle'=>'Cập nhật họ tên, email và giới thiệu bản thân của chính bạn.'],
];
if (!isset($catalog[$slug])) {
    http_response_code(404);
    echo '<div class="alert alert-warning">Chức năng này chưa có màn hình sử dụng riêng.</div>';
    return;
}
$entry=$catalog[$slug];
$env=(string)($_GET['env']??'beta');
if(!in_array($env,Policy::ENVS,true)) $env='beta';
if(($actor['role']??'')==='tester') $env='beta';
$projects=array_values(array_filter($db['projects']??[],static fn(array $p):bool => ProjectAccess::canView($db,$actor,(int)$p['id']) && ReviewWorkflow::projectIsPublished($p)));
$projectId=(int)($_GET['project']??($projects[0]['id']??0));
$project=null;
foreach($projects as $candidate) if((int)$candidate['id']===$projectId){$project=$candidate;break;}
if(!$project){ http_response_code(403);echo '<div class="alert alert-warning">Dự án chưa được phát hành hoặc bạn không có quyền xem.</div>';return; }

// Existing account profile is available to the account owner; when a profile feature flag
// exists in this project, the feature route obeys that flag as well.
$profileFlag = null;
if ($slug==='profile') {
    foreach ($db['features']??[] as $f) {
        if ((int)$f['project_id']!==$projectId) continue;
        $key=(string)($f['key']??'');
        $name=(string)($f['name']??'');
        if(in_array($key,['edit_profile','profile_edit','update_profile','sua_thong_tin_ca_nhan'],true) || str_contains(mb_strtolower($name,'UTF-8'),'thông tin cá nhân')){$profileFlag=$f;break;}
    }
}
$enabled=$slug==='profile' && $profileFlag===null
    ? true
    : CommunityService::enabled($db,$projectId,$env,$slug==='profile'?(string)$profileFlag['key']:$entry['flag'],$actor);
if (!$enabled){
    http_response_code(403);
    echo '<div class="alert alert-warning"><strong>Chức năng chưa được cấp quyền.</strong> Phiên bản '.$env.' chưa mở tính năng này cho tài khoản của bạn. <a href="/?p=preview">Quay về Web mẫu</a></div>';
    return;
}

$base='/?p=feature_app&project='.$projectId.'&env='.rawurlencode($env).'&feature=';
$preferences=$db['community_preferences'][(string)$actor['id']]??[];
$dark=!empty($preferences['dark']);
$dates=static function (string $date):string {
    try{return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y · H:i');}
    catch(Throwable){return $date;}
};
$catNames=['technology'=>'Công nghệ','community'=>'Cộng đồng','workshop'=>'Workshop','other'=>'Khác'];
$events=array_values(array_filter($db['community_events']??[],static fn(array $e):bool=>(int)$e['project_id']===$projectId));
usort($events,static fn(array $a,array $b):int=>strcmp($a['starts_at'],$b['starts_at']));
$allRegs=$db['community_registrations']??[];
$mine=array_values(array_filter($allRegs,static fn(array $r):bool=>(int)$r['user_id']===(int)$actor['id']&&in_array($r['status'],['confirmed','waitlisted'],true)));
$notes=array_values(array_filter($db['community_notifications']??[],static fn(array $n):bool=>(int)$n['user_id']===(int)$actor['id']));
usort($notes,static fn($a,$b)=>(int)$b['id']<=>(int)$a['id']);
$unread=count(array_filter($notes,static fn($n)=>empty($n['read_at'])));
$canManage=(($actor['role']??'')==='developer')&&ProjectAccess::canEdit($db,$actor,$projectId);
$links=[
    'register'=>'Đăng ký sự kiện','waitlist'=>'Danh sách chờ','dark'=>'Giao diện tối',
    'search'=>'Tìm kiếm nâng cao','notifications'=>'Thông báo','profile'=>'Hồ sơ',
];
?>
<section class="ff-feature-shell <?= $slug==='dark' && $dark ? 'ff-feature-dark':'' ?>" data-feature-slug="<?=h($slug)?>" data-dark-active="<?= $slug==='dark'&&$dark?'1':'0' ?>">
    <div class="ff-feature-hero">
        <div><span class="ff-feature-eyebrow"><?=h(strtoupper($project['name']))?> / <?=h(strtoupper($env))?></span>
            <h2><i class="bi bi-<?=h($entry['icon'])?>"></i> <?=h($entry['title'])?></h2>
            <p><?=h($entry['subtitle'])?></p>
        </div>
        <a class="btn btn-light btn-sm" href="/?p=preview&project=<?=$projectId?>&env=<?=h($env)?>"><i class="bi bi-arrow-left"></i> Trở về các tính năng</a>
    </div>
    <nav class="ff-feature-tabs" aria-label="Các tính năng của dự án">
        <?php foreach($links as $key=>$label):
            // Do not advertise a restricted feature as available.
            $ruleFlag=$catalog[$key]['flag'];
            $allowed = $key==='profile' && $profileFlag===null
                ? true
                : CommunityService::enabled($db,$projectId,$env,$key==='profile'?(string)$profileFlag['key']:$ruleFlag,$actor);
            if(!$allowed)continue;
        ?>
        <a class="<?= $key===$slug?'active':'' ?>" href="<?=h($base.$key)?>"><?=h($label)?></a>
        <?php endforeach; ?>
    </nav>

<?php if($slug==='register'): ?>
    <div class="ff-feature-stats">
        <div><span>Tổng sự kiện</span><strong><?=count($events)?></strong></div>
        <div><span>Đã xác nhận của tôi</span><strong><?=count(array_filter($mine,static fn($r)=>$r['status']==='confirmed'))?></strong></div>
        <div><span>Đang chờ</span><strong><?=count(array_filter($mine,static fn($r)=>$r['status']==='waitlisted'))?></strong></div>
    </div>
    <?php if($canManage): ?>
    <details class="ff-feature-card mb-3"><summary class="fw-bold">+ Developer: Tạo sự kiện mới</summary>
        <form action="/" method="post" class="row g-3 mt-2">
            <?=csrf_field()?><input type="hidden" name="action" value="community_event_create"><input type="hidden" name="project_id" value="<?=$projectId?>"><input type="hidden" name="return_to_feature" value="register">
            <div class="col-md-8"><label class="form-label">Tên sự kiện</label><input name="name" class="form-control" minlength="5" maxlength="120" required></div>
            <div class="col-md-4"><label class="form-label">Danh mục</label><select name="category" class="form-select"><?php foreach($catNames as $k=>$val):?><option value="<?=h($k)?>"><?=h($val)?></option><?php endforeach;?></select></div>
            <div class="col-md-5"><label class="form-label">Địa điểm</label><input name="location" class="form-control" minlength="3" maxlength="120" required></div>
            <div class="col-md-4"><label class="form-label">Thời gian</label><input type="datetime-local" name="starts_at" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Sức chứa</label><input type="number" name="capacity" value="30" min="1" max="5000" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Mô tả</label><textarea name="description" class="form-control" rows="2" maxlength="600"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Tạo sự kiện</button></div>
        </form>
    </details>
    <?php endif; ?>
    <div class="row g-3">
    <?php foreach($events as $e): $id=(int)$e['id'];$stats=CommunityService::status($db,$id);$left=max(0,(int)$e['capacity']-$stats['confirmed']);$reg=CommunityService::registration($db,$id,(int)$actor['id']);$past=strtotime($e['starts_at'])<time();$waitAllowed=CommunityService::enabled($db,$projectId,$env,'smart_waitlist',$actor); ?>
        <div class="col-md-6 col-xl-4"><article class="ff-feature-card h-100" id="event-<?=$id?>">
            <div class="d-flex justify-content-between gap-2"><span class="badge text-bg-light border"><?=h($catNames[$e['category']]??'Khác')?></span><span class="badge <?=$left?'text-bg-success':'text-bg-warning'?>"><?=$left?'Còn '.$left.' chỗ':'Hết chỗ'?></span></div>
            <h3><?=h($e['name'])?></h3><p class="text-secondary small"><?=h($e['description']??'')?></p>
            <p class="ff-feature-meta"><i class="bi bi-calendar-event"></i> <?=h($dates($e['starts_at']))?></p>
            <p class="ff-feature-meta"><i class="bi bi-geo-alt"></i> <?=h($e['location'])?></p>
            <p class="ff-feature-meta"><i class="bi bi-people"></i> <?=$stats['confirmed']?> / <?=(int)$e['capacity']?> đã xác nhận · <?=$stats['waitlisted']?> chờ</p>
            <?php if($reg): ?>
                <div class="alert <?=$reg['status']==='confirmed'?'alert-success':'alert-info'?> py-2 small"><?=$reg['status']==='confirmed'?'Bạn đã đăng ký thành công':'Bạn đã vào danh sách chờ'?></div>
                <form action="/" method="post"><?=csrf_field()?><input type="hidden" name="action" value="community_cancel"><input type="hidden" name="event_id" value="<?=$id?>"><input type="hidden" name="return_to_feature" value="register"><button class="btn btn-outline-danger w-100">Hủy đăng ký</button></form>
            <?php elseif(($actor['role']??'')==='tester'&&!$past&&($left>0||$waitAllowed)): ?>
                <form action="/" method="post"><?=csrf_field()?><input type="hidden" name="action" value="community_register"><input type="hidden" name="event_id" value="<?=$id?>"><input type="hidden" name="environment" value="<?=h($env)?>"><input type="hidden" name="return_to_feature" value="register"><button class="btn <?=$left?'btn-primary':'btn-outline-primary'?> w-100"><?=$left?'Đăng ký tham dự':'Tham gia danh sách chờ'?></button></form>
            <?php else: ?><div class="alert alert-light border small mb-0"><?= $past?'Sự kiện đã kết thúc':(($actor['role']??'')==='tester'?'Không còn chỗ / chưa bật danh sách chờ':'Chỉ tài khoản Tester đăng ký') ?></div><?php endif; ?>
        </article></div>
    <?php endforeach; ?>
    </div>
    <?php if(!$events): ?><div class="ff-feature-card">Chưa có sự kiện. Developer có thể tạo sự kiện tại đây.</div><?php endif; ?>

<?php elseif($slug==='waitlist'): ?>
    <div class="ff-feature-card"><h3>Danh sách chờ của tôi</h3><p class="text-secondary">Vị trí được tính theo thứ tự đăng ký; người đứng đầu sẽ được xác nhận khi có một chỗ trống do người khác hủy.</p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sự kiện</th><th>Trạng thái</th><th>Vị trí chờ</th><th>Số người chờ</th><th>Thao tác</th></tr></thead><tbody>
        <?php $rows=0;foreach($events as $e):$id=(int)$e['id'];$reg=CommunityService::registration($db,$id,(int)$actor['id']);if(!$reg)continue;$rows++;$waiters=array_values(array_filter($allRegs,static fn($r)=>(int)$r['event_id']===$id && $r['status']==='waitlisted'));usort($waiters,static fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);$position='—';foreach($waiters as $i=>$r)if((int)$r['user_id']===(int)$actor['id']){$position=(string)($i+1);break;} ?>
        <tr><td><strong><?=h($e['name'])?></strong><br><small class="text-secondary"><?=h($dates($e['starts_at']))?></small></td><td><span class="badge <?=$reg['status']==='confirmed'?'text-bg-success':'text-bg-info'?>"><?=$reg['status']==='confirmed'?'Đã xác nhận':'Đang chờ'?></span></td><td><strong><?=h($position)?></strong></td><td><?=count($waiters)?></td><td><form method="post" action="/"><?=csrf_field()?><input type="hidden" name="action" value="community_cancel"><input type="hidden" name="event_id" value="<?=$id?>"><input type="hidden" name="return_to_feature" value="waitlist"><button class="btn btn-sm btn-outline-danger">Hủy</button></form></td></tr>
        <?php endforeach; ?>
        <?php if(!$rows):?><tr><td colspan="5" class="text-secondary py-4">Bạn chưa có đăng ký nào. <a href="<?=h($base.'register')?>">Chọn sự kiện để đăng ký</a>.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    <div class="ff-feature-card mt-3"><h3>Sự kiện đang hết chỗ</h3><div class="row g-3">
        <?php $fullCount=0;foreach($events as $e):$stats=CommunityService::status($db,(int)$e['id']);if($stats['confirmed']<(int)$e['capacity']||strtotime($e['starts_at'])<time())continue;$fullCount++; ?>
        <div class="col-md-6"><div class="ff-waitlist-event"><strong><?=h($e['name'])?></strong><small><?=$stats['waitlisted']?> người đang chờ</small>
            <?php if(($actor['role']??'')==='tester'&&!CommunityService::registration($db,(int)$e['id'],(int)$actor['id']) && CommunityService::enabled($db,$projectId,$env,'new_registration',$actor)):?>
            <form method="post" action="/"><?=csrf_field()?><input type="hidden" name="action" value="community_register"><input type="hidden" name="event_id" value="<?=(int)$e['id']?>"><input type="hidden" name="environment" value="<?=h($env)?>"><input type="hidden" name="return_to_feature" value="waitlist"><button class="btn btn-outline-primary btn-sm mt-2">Vào danh sách chờ</button></form>
            <?php endif; ?></div></div>
        <?php endforeach; ?>
        <?php if(!$fullCount):?><p class="text-secondary mb-0">Hiện chưa có sự kiện nào hết chỗ.</p><?php endif; ?></div></div>

<?php elseif($slug==='dark'): ?>
    <div class="ff-feature-card"><h3>Trải nghiệm giao diện <?=$dark?'tối':'sáng'?></h3><p class="text-secondary">Sở thích được lưu trong tài khoản và áp dụng cho không gian trải nghiệm này khi bạn mở lại.</p>
        <div class="ff-theme-preview"><span class="ff-theme-orb"><i class="bi bi-<?=$dark?'moon-stars':'sun'?>"></i></span><h3>Xin chào, <?=h($actor['name'])?></h3><p>Đây là bản xem trước trực tiếp của giao diện CommunityPass.</p><div class="ff-theme-preview-list"><span>Thẻ thông tin</span><span>Đang hoạt động</span></div></div>
        <form method="post" action="/" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="community_preference"><input type="hidden" name="dark" value="<?=$dark?'0':'1'?>"><input type="hidden" name="project_id" value="<?=$projectId?>"><input type="hidden" name="environment" value="<?=h($env)?>"><input type="hidden" name="return_to_feature" value="dark"><button class="btn btn-primary btn-lg"><i class="bi bi-<?=$dark?'sun':'moon-stars'?>"></i> Chuyển sang chế độ <?=$dark?'sáng':'tối'?></button></form>
    </div>

<?php elseif($slug==='search'): ?>
    <?php $q=trim(mb_substr((string)($_GET['q']??''),0,100));$category=(string)($_GET['category']??'all');if(!in_array($category,array_merge(['all'],array_keys($catNames)),true))$category='all';$normalized=static function(string $t):string {$t=str_replace(['đ','Đ'],['d','D'],$t);$converted=function_exists('iconv')?@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$t):false;return mb_strtolower($converted!==false?$converted:$t,'UTF-8');};$matched=array_values(array_filter($events,static function(array $e)use($q,$category,$catNames,$normalized):bool {if($category!=='all' && $e['category']!==$category)return false;if($q==='')return true;return str_contains($normalized($e['name'].' '.($e['description']??'').' '.($e['location']??'').' '.($catNames[$e['category']]??'')),$normalized($q));}));?>
    <div class="ff-feature-card"><h3>Tìm kiếm sự kiện</h3><form action="/" method="get" class="row g-3 align-items-end"><input type="hidden" name="p" value="feature_app"><input type="hidden" name="feature" value="search"><input type="hidden" name="project" value="<?=$projectId?>"><input type="hidden" name="env" value="<?=h($env)?>"><div class="col-lg-7"><label class="form-label">Từ khóa</label><input type="search" class="form-control form-control-lg" name="q" maxlength="100" value="<?=h($q)?>" placeholder="Tên, nội dung hoặc địa điểm..."></div><div class="col-lg-3"><label class="form-label">Danh mục</label><select class="form-select form-select-lg" name="category"><option value="all">Tất cả</option><?php foreach($catNames as $k=>$v):?><option value="<?=h($k)?>" <?=$category===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div><div class="col-lg-2 d-grid"><button class="btn btn-primary btn-lg">Tìm kiếm</button></div></form></div>
    <div class="ff-feature-card mt-3"><h3><?=count($matched)?> kết quả phù hợp</h3><div class="ff-results-list">
        <?php foreach($matched as $e):$stats=CommunityService::status($db,(int)$e['id']);?>
        <article><div><strong><?=h($e['name'])?></strong><p><?=h($e['description']??'')?></p><small><i class="bi bi-geo-alt"></i> <?=h($e['location'])?> · <?=h($dates($e['starts_at']))?></small></div><div><span class="badge text-bg-light border"><?=h($catNames[$e['category']]??'Khác')?></span><a class="btn btn-outline-primary btn-sm ms-2" href="<?=h($base.'register')?>#event-<?=(int)$e['id']?>">Xem sự kiện</a></div></article>
        <?php endforeach;?>
        <?php if(!$matched):?><p class="text-secondary">Không có sự kiện phù hợp; hãy đổi bộ lọc hoặc từ khóa.</p><?php endif;?></div></div>

<?php elseif($slug==='notifications'): ?>
    <div class="ff-feature-stats"><div><span>Tổng thông báo</span><strong><?=count($notes)?></strong></div><div><span>Chưa đọc</span><strong><?=$unread?></strong></div><div><span>Đã đọc</span><strong><?=count($notes)-$unread?></strong></div></div>
    <div class="ff-feature-card"><div class="d-flex justify-content-between align-items-center"><h3>Thông báo của tôi</h3><span class="badge text-bg-primary"><?=$unread?> chưa đọc</span></div>
        <?php foreach($notes as $note):?><div class="ff-notice-row <?=$note['read_at']?'':'is-unread'?>"><div class="ff-notice-dot"><i class="bi bi-bell"></i></div><div class="flex-grow-1"><strong><?=h($note['message'])?></strong><p><?=h($dates($note['created_at']))?></p></div><?php if(!$note['read_at']):?><form method="post" action="/"><?=csrf_field()?><input type="hidden" name="action" value="community_notify_read"><input type="hidden" name="notification_id" value="<?=(int)$note['id']?>"><input type="hidden" name="return_to_feature" value="notifications"><input type="hidden" name="project_id" value="<?=$projectId?>"><button class="btn btn-outline-primary btn-sm">Đánh dấu đã đọc</button></form><?php else:?><span class="text-secondary small">Đã đọc</span><?php endif;?></div><?php endforeach;?>
        <?php if(!$notes):?><p class="text-secondary">Chưa có thông báo nào. Hệ thống sẽ ghi nhận khi bạn đăng ký, hủy hoặc được chuyển từ danh sách chờ.</p><?php endif;?>
    </div>
    <div class="alert alert-info mt-3"><i class="bi bi-info-circle"></i> Đây là thông báo trong ứng dụng, có trạng thái đã đọc/chưa đọc. Chưa tích hợp dịch vụ push đến điện thoại khi đóng trình duyệt.</div>

<?php elseif($slug==='profile'): ?>
    <?php $account=ProfileService::findUser($db,(int)$actor['id']); ?>
    <div class="ff-feature-card"><h3>Sửa thông tin của bạn</h3><p class="text-secondary">Chỉ tài khoản đang đăng nhập có thể cập nhật hồ sơ của chính mình.</p>
        <form method="post" action="/" class="row g-3"><?=csrf_field()?><input type="hidden" name="action" value="profile_update"><input type="hidden" name="return_to_feature" value="profile"><input type="hidden" name="project_id" value="<?=$projectId?>"><div class="col-lg-6"><label class="form-label">Họ tên</label><input class="form-control" name="name" minlength="2" maxlength="80" required value="<?=h($account['name'])?>"></div><div class="col-lg-6"><label class="form-label">Tên đăng nhập</label><input class="form-control" value="<?=h($account['username'])?>" disabled></div><div class="col-12"><label class="form-label">Email liên hệ</label><input type="email" class="form-control" name="email" maxlength="254" placeholder="example@email.com" value="<?=h($account['email']??'')?>"></div><div class="col-12"><label class="form-label">Giới thiệu</label><textarea class="form-control" name="bio" rows="3" maxlength="300"><?=h($account['bio']??'')?></textarea></div><div class="col-12"><button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Lưu hồ sơ</button> <a class="btn btn-outline-secondary btn-lg" href="/?p=profile">Quản lý ảnh đại diện và mật khẩu</a></div></form>
    </div>
<?php endif; ?>
</section>
