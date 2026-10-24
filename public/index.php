<?php
declare(strict_types=1);
ini_set('display_errors','0');
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
    session_start();
}
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'self'");
require __DIR__.'/../app/bootstrap.php';
$page=(string)($_GET['p']??'dashboard');
try { $db=store()->read(); }
catch(Throwable $e) { http_response_code(503); exit('Chưa khởi tạo dự án. Chạy: php scripts/setup.php'); }
if ($page === 'export' && logged()) {
    $id=(int)($_GET['version']??0);
    try {
        $v=VersionService::get($db,$id);
        ProjectAccess::requireView($db,user(),(int)$v['project_id']);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="flagflow-config-'.$id.'.json"');
        header('Cache-Control: no-store');
        echo VersionService::exportJson($v);exit;
    }catch(Throwable){http_response_code(404);exit('Không tìm thấy phiên bản');}
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if(!hash_equals(csrf(),(string)($_POST['csrf']??''))) throw new DomainException('Phiên biểu mẫu đã hết hạn. Vui lòng thử lại');
    $action=(string)($_POST['action']??'');
    if($action==='login') {
      $username=trim((string)($_POST['username']??'')); $password=(string)($_POST['password']??'');
      $found=null; foreach($db['users'] as $u) if($u['username']===$username) $found=$u;
      if(!$found || !password_verify($password,$found['password_hash'])) throw new DomainException('Tên đăng nhập hoặc mật khẩu không đúng');
      session_regenerate_id(true); $_SESSION['user']=['id'=>$found['id'],'name'=>$found['name'],'username'=>$found['username'],'role'=>$found['role']];alert('Đăng nhập thành công');redirect();
    }
    if($action==='register') {
      if(logged())throw new DomainException('Vui lòng đăng xuất trước khi đăng ký');
      if(!hash_equals((string)($_POST['password']??''),(string)($_POST['confirm_password']??'')))throw new DomainException('Mật khẩu xác nhận không khớp');
      $uname=(string)($_POST['username']??'');$name=(string)($_POST['name']??'');$pass=(string)($_POST['password']??'');
      $role=(string)($_POST['role']??'tester');
      if($role === 'developer') {
        $devCode = trim((string)($_POST['dev_code'] ?? ''));
        if($devCode !== 'DEV2026') {
          throw new DomainException('Mã kích hoạt Developer không chính xác. Chỉ lập trình viên nội bộ mới có mã này (Mã: DEV2026)');
        }
      } else {
        $role = 'tester';
      }
      store()->transaction(function(&$d)use($uname,$name,$pass,$role){
        $uid=AccountService::create($d,$uname,$name,$pass,$role);
        if($role==='developer'){foreach($d['projects']??[] as $p)ProjectAccess::grant($d,(int)$p['id'],$uid,'editor');}
        elseif($role==='tester'){foreach($d['projects']??[] as $p)ProjectAccess::grant($d,(int)$p['id'],$uid,'viewer');}
      });
      alert('Đăng ký tài khoản '.$role.' thành công! Hãy đăng nhập với thông tin vừa tạo.');redirect('login');
    }
    if(!logged()) throw new DomainException('Bạn cần đăng nhập');
    if($action==='account_create') {
      if(!is_admin())throw new DomainException('Chỉ Admin có thể tạo tài khoản nội bộ');
      if(!hash_equals((string)($_POST['password']??''),(string)($_POST['confirm_password']??'')))throw new DomainException('Mật khẩu xác nhận không khớp');
      $uname=(string)($_POST['username']??'');$name=(string)($_POST['name']??'');$pass=(string)($_POST['password']??'');$role=(string)($_POST['role']??'tester');
      store()->transaction(function(&$d)use($uname,$name,$pass,$role){AccountService::create($d,$uname,$name,$pass,$role);audit($d,'account.create',$uname.' ('.$role.')');});
      alert('Đã tạo tài khoản. Hãy chuyển mật khẩu riêng cho người dùng');redirect('accounts');
    }
    if($action==='account_password') {
      if(!is_admin())throw new DomainException('Chỉ Admin có thể đặt lại mật khẩu');
      $id=(int)($_POST['user_id']??0);$pass=(string)($_POST['password']??'');
      if(!hash_equals($pass,(string)($_POST['confirm_password']??'')))throw new DomainException('Mật khẩu xác nhận không khớp');
      store()->transaction(function(&$d)use($id,$pass){AccountService::changePassword($d,$id,$pass);audit($d,'account.password','Đặt lại cho user_id='.$id);});
      if(user()['id']===$id){session_regenerate_id(true);}
      alert('Đã đổi mật khẩu tài khoản');redirect('accounts');
    }
    if($action==='logout') { session_unset(); session_destroy();header('Location: /?p=login',true,303);exit; }
    if($action==='project_member_grant') {
      if(!is_admin())throw new DomainException('Chỉ Admin phân quyền dự án');
      $projectId=(int)($_POST['project_id']??0);$memberId=(int)($_POST['user_id']??0);$permission=(string)($_POST['permission']??'');
      store()->transaction(function(&$d)use($projectId,$memberId,$permission){VersionService::ensure($d);ProjectAccess::grant($d,$projectId,$memberId,$permission);audit($d,'project.permission',"project=$projectId user=$memberId role=$permission");});
      alert('Đã cập nhật quyền theo dự án');redirect('projects');
    }
    if($action==='project_member_revoke') {
      if(!is_admin())throw new DomainException('Chỉ Admin có thể gỡ thành viên khỏi dự án');
      $projectId=(int)($_POST['project_id']??0);$memberId=(int)($_POST['user_id']??0);
      store()->transaction(function(&$d)use($projectId,$memberId){ProjectAccess::revoke($d,$projectId,$memberId);audit($d,'project.member_revoke',"project=$projectId user=$memberId");});
      alert('Đã gỡ quyền thành viên khỏi dự án');redirect('projects');
    }
    if($action==='version_draft_create') {
      $projectId=(int)($_POST['project_id']??0);$env=(string)($_POST['environment']??'');
      $reason=trim((string)($_POST['reason']??'Tạo nháp thay đổi'));
      if(!in_array($env,Policy::ENVS,true))throw new DomainException('Môi trường không hợp lệ');
      $id=store()->transaction(function(&$d)use($projectId,$env,$reason){VersionService::ensure($d);ProjectAccess::requireEdit($d,user(),$projectId);$id=VersionService::createDraft($d,$projectId,$env,user()['id'],$reason);audit($d,'version.draft',"version=$id");return $id;});
      alert('Đã tạo bản nháp. Có thể thay đổi tỷ lệ và danh sách ưu tiên.');header('Location: /?p=versions&id='.$id,true,303);exit;
    }
    if($action==='version_rule_update') {
      $vid=(int)($_POST['version_id']??0);$fid=(int)($_POST['feature_id']??0);
      $changes=['enabled'=>(string)($_POST['enabled']??'0')==='1','percentage'=>$_POST['percentage']??'0','group_id'=>($_POST['group_id']??'')===''?null:(int)$_POST['group_id'],'allow_users'=>(string)($_POST['allow_users']??''),'deny_users'=>(string)($_POST['deny_users']??'')];
      store()->transaction(function(&$d)use($vid,$fid,$changes){$v=VersionService::get($d,$vid);ProjectAccess::requireEdit($d,user(),(int)$v['project_id']);VersionService::updateDraft($d,$vid,$fid,$changes);audit($d,'version.rule',"version=$vid feature=$fid");});
      alert('Đã lưu quy tắc vào bản nháp');header('Location: /?p=versions&id='.$vid,true,303);exit;
    }
    if($action==='version_publish') {
      if(!is_admin())throw new DomainException('Chỉ Admin được phát hành phiên bản');
      $vid=(int)($_POST['version_id']??0);$reason=trim((string)($_POST['reason']??''));
      store()->transaction(function(&$d)use($vid,$reason){VersionService::publish($d,$vid,user()['id'],$reason);audit($d,'version.publish',"version=$vid reason=$reason");});
      alert('Đã phát hành bản mới; bản cũ vẫn được lưu nguyên vẹn');header('Location: /?p=versions&id='.$vid,true,303);exit;
    }
    if($action==='version_rollback') {
      if(!is_admin())throw new DomainException('Chỉ Admin được khôi phục phiên bản');
      $vid=(int)($_POST['version_id']??0);$reason=trim((string)($_POST['reason']??''));
      $new=store()->transaction(function(&$d)use($vid,$reason){$new=VersionService::rollback($d,$vid,user()['id'],$reason);audit($d,'version.rollback',"restored=$vid published=$new");return $new;});
      alert('Đã khôi phục bằng bản phát hành MỚI; lịch sử vẫn còn');header('Location: /?p=versions&id='.$new,true,303);exit;
    }
    if($action==='version_import') {
      $pid=(int)($_POST['project_id']??0);$env=(string)($_POST['environment']??'');
      if(!in_array($env,Policy::ENVS,true))throw new DomainException('Môi trường không hợp lệ');
      $file=$_FILES['configuration']??null;
      if(!is_array($file)||$file['error']!==UPLOAD_ERR_OK||$file['size']>150000||!is_uploaded_file($file['tmp_name']))throw new DomainException('Tệp nhập sai hoặc quá lớn');
      $raw=file_get_contents($file['tmp_name']);
      $id=store()->transaction(function(&$d)use($pid,$env,$raw){ProjectAccess::requireEdit($d,user(),$pid);$id=VersionService::importDraft($d,$pid,$env,user()['id'],$raw);audit($d,'version.import',"version=$id");return $id;});
      alert('Đã nhập cấu hình thành bản NHÁP, không tự phát hành');header('Location: /?p=versions&id='.$id,true,303);exit;
    }
    if($action==='project_create') {
      if(!can_edit()) throw new DomainException('Bạn không có quyền tạo dự án');
      $name=trim((string)($_POST['name']??''));$key=trim((string)($_POST['key']??''));$desc=trim((string)($_POST['description']??''));
      if(mb_strlen($name)<3||mb_strlen($name)>80||!ident($key))throw new DomainException('Tên 3–80 ký tự; mã gồm chữ thường, số, dấu _ (3–40 ký tự)');
      store()->transaction(function(&$d)use($name,$key,$desc){foreach($d['projects'] as $p)if($p['key']===$key)throw new DomainException('Mã dự án đã tồn tại');$projectId=Store::next($d,'projects');$d['projects'][]=['id'=>$projectId,'name'=>$name,'key'=>$key,'description'=>$desc];VersionService::ensure($d);ProjectAccess::grant($d,$projectId,user()['id'],'manager');audit($d,'project.create',$key);});alert('Đã tạo dự án');redirect('projects');
    }
    if($action==='feature_submit') {
      if(!can_edit())throw new DomainException('Bạn không có quyền gửi tính năng');
      $projectId=(int)($_POST['project_id']??0);
      $key=trim((string)($_POST['key']??''));$name=trim((string)($_POST['name']??''));
      $description=trim((string)($_POST['description']??''));$environment=(string)($_POST['environment']??'beta');
      $groupId=($_POST['group_id']??'')===''?null:(int)$_POST['group_id'];
      $reason=trim((string)($_POST['reason']??''));
      store()->transaction(function(&$d)use($projectId,$key,$name,$description,$environment,$groupId,$reason){
        ProjectAccess::requireEdit($d,user(),$projectId);
        $requestId=FeatureSubmission::submit($d,user(),$projectId,$key,$name,$description,$environment,$groupId,$reason);
        VersionService::publishCurrent($d,$projectId,'development',user()['id'],'Kiểm thử tính năng mới trong Development');
        audit($d,'feature.submit',"request=$requestId key=$key environment=$environment");
      });
      alert('Đã tạo tính năng trong Development và gửi yêu cầu đến Admin. Tính năng chưa bật ở môi trường đích.');
      redirect('approvals');
    }
    if($action==='feature_create') {
      if(!can_edit())throw new DomainException('Không đủ quyền');
      $projectId=(int)($_POST['project_id']??0);$key=trim((string)($_POST['key']??''));$name=trim((string)($_POST['name']??''));$desc=trim((string)($_POST['description']??''));
      if(!ident($key)||mb_strlen($name)<3||mb_strlen($name)>90)throw new DomainException('Tên/mã tính năng không hợp lệ');
      store()->transaction(function(&$d)use($projectId,$key,$name,$desc){get_by_id($d,'projects',$projectId);ProjectAccess::requireEdit($d,user(),$projectId);foreach($d['features'] as $f)if($f['project_id']===$projectId&&$f['key']===$key)throw new DomainException('Mã tính năng trùng trong dự án');$id=Store::next($d,'features');$d['features'][]=['id'=>$id,'project_id'=>$projectId,'name'=>$name,'key'=>$key,'description'=>$desc];foreach(Policy::ENVS as $env)set_config($d,$id,$env,false,null);VersionService::publishCurrent($d,$projectId,'development',user()['id'],'Thêm tính năng mới mặc định tắt');audit($d,'feature.create',$key);});alert('Đã tạo tính năng (mặc định tắt ở mọi môi trường)');redirect('features');
    }
    if($action==='group_create') {
      if(!can_edit())throw new DomainException('Không đủ quyền');$projectId=(int)($_POST['project_id']??0);$name=trim((string)($_POST['name']??''));
      if(mb_strlen($name)<3||mb_strlen($name)>80)throw new DomainException('Tên nhóm 3–80 ký tự');
      store()->transaction(function(&$d)use($projectId,$name){get_by_id($d,'projects',$projectId);ProjectAccess::requireEdit($d,user(),$projectId);$d['groups'][]=['id'=>Store::next($d,'groups'),'project_id'=>$projectId,'name'=>$name,'description'=>''];audit($d,'group.create',$name);});alert('Đã tạo nhóm');redirect('groups');
    }
    if($action==='membership_add' || $action==='membership_remove') {
      if(!is_admin())throw new DomainException('Chỉ Admin quản lý thành viên nhóm');$groupId=(int)($_POST['group_id']??0);$userId=(int)($_POST['user_id']??0);
      store()->transaction(function(&$d)use($action,$groupId,$userId){get_by_id($d,'groups',$groupId);get_by_id($d,'users',$userId);$d['memberships']=array_values(array_filter($d['memberships'],fn($m)=>!($m['group_id']===$groupId&&$m['user_id']===$userId)));if($action==='membership_add')$d['memberships'][]=['group_id'=>$groupId,'user_id'=>$userId];audit($d,'group.membership',"$action group=$groupId user=$userId");});alert('Đã cập nhật nhóm');redirect('groups');
    }
    if($action==='config_change') {
      if(!can_edit())throw new DomainException('Không đủ quyền');$featureId=(int)($_POST['feature_id']??0);$env=(string)($_POST['environment']??'');$enabled=(string)($_POST['enabled']??'')==='1';$groupId=($_POST['group_id']??'')===''?null:(int)$_POST['group_id'];
      if(!in_array($env,Policy::ENVS,true))throw new DomainException('Môi trường không hợp lệ');
      $reason=trim((string)($_POST['reason']??''));
      if(strlen($reason)>500)throw new DomainException('Lý do quá dài');
      store()->transaction(function(&$d)use($featureId,$env,$enabled,$groupId,$reason){$feature=get_by_id($d,'features',$featureId);ProjectAccess::requireEdit($d,user(),(int)$feature['project_id']);valid_group($d,$feature['project_id'],$groupId);
        if(Policy::canDirectToggle(user()['role'],$env)) {set_config($d,$featureId,$env,$enabled,$groupId);VersionService::publishCurrent($d,(int)$feature['project_id'],$env,user()['id'],'Cập nhật Development: '.$reason,$featureId);audit($d,'config.direct',"$env feature=$featureId enabled=".(int)$enabled);return;}
        if(!Policy::canRequest(user()['role'],$env))throw new DomainException('Không thể tạo yêu cầu');
        if(mb_strlen($reason)<5)throw new DomainException('Beta/Production cần lý do từ 5 ký tự');
        foreach($d['requests'] as $r)if($r['feature_id']===$featureId&&$r['environment']===$env&&$r['status']==='pending')throw new DomainException('Đã có yêu cầu chờ duyệt cho tính năng và môi trường này');
        $d['requests'][]=['id'=>Store::next($d,'requests'),'feature_id'=>$featureId,'environment'=>$env,'enabled'=>$enabled,'group_id'=>$groupId,'reason'=>$reason,'status'=>'pending','requested_by'=>user()['id'],'reviewed_by'=>null,'review_note'=>'','created_at'=>date('c'),'reviewed_at'=>null];audit($d,'request.create',"$env feature=$featureId");
      });alert($env==='development'?'Đã cập nhật Development':'Đã gửi yêu cầu, chờ Admin khác duyệt');redirect('features');
    }
    if($action==='review') {
      if(!is_admin())throw new DomainException('Chỉ Admin được duyệt');$id=(int)($_POST['request_id']??0);$decision=(string)($_POST['decision']??'');$note=trim((string)($_POST['note']??''));
      if(!in_array($decision,['approved','rejected'],true))throw new DomainException('Quyết định không hợp lệ');
      store()->transaction(function(&$d)use($id,$decision,$note){foreach($d['requests'] as &$r)if($r['id']===$id){if(!Policy::canApprove(user(),$r))throw new DomainException('Bạn không thể duyệt yêu cầu này (không được tự duyệt)');$f=get_by_id($d,'features',$r['feature_id']);valid_group($d,$f['project_id'],$r['group_id']);$r['status']=$decision;$r['reviewed_by']=user()['id'];$r['review_note']=$note;$r['reviewed_at']=date('c');if($decision==='approved'){set_config($d,$r['feature_id'],$r['environment'],$r['enabled'],$r['group_id']);VersionService::publishCurrent($d,(int)$f['project_id'],$r['environment'],user()['id'],'Duyệt yêu cầu #'.$id.': '.$r['reason'],(int)$r['feature_id']);}audit($d,'request.review',"request=$id status=$decision");return;}throw new DomainException('Không tìm thấy yêu cầu');});alert($decision==='approved'?'Đã duyệt và áp dụng cấu hình':'Đã từ chối yêu cầu');redirect('approvals');
    }
    throw new DomainException('Hành động không hợp lệ');
  }catch(Throwable $e){ alert($e instanceof DomainException?$e->getMessage():'Có lỗi hệ thống, vui lòng kiểm tra nhật ký máy chủ','error');redirect(logged() && !in_array($action,['login','register'],true)?(string)($_POST['back']??'dashboard'):'login'); }
}
if(!logged() && !in_array($page,['login','register'],true))redirect('login');
if(logged() && in_array($page,['login','register'],true))redirect();
if($page==='logout')redirect();
if(logged()) {
  if(in_array($page, ['audit', 'accounts'], true) && !is_admin()) {
    alert('Chỉ Quản trị viên (Admin) mới có quyền truy cập trang này', 'error');
    redirect('dashboard');
  }
  if(in_array($page, ['submit', 'groups', 'versions', 'api_info', 'approvals'], true) && !can_edit()) {
    alert('Tài khoản Tester chỉ có quyền xem tính năng và trải nghiệm web mẫu', 'error');
    redirect('dashboard');
  }
}
$envLabels=['development'=>'Development','beta'=>'Beta','production'=>'Production'];
function name_of(array $rows,int $id,string $key='name'): string {return (Policy::find($rows,$id)??[])[$key]??'#'.$id;}
function config_of(array $db,int $fid,string $env): ?array {foreach($db['configs'] as $c)if($c['feature_id']===$fid&&$c['environment']===$env)return $c;return null;}
function pill(string $text,string $kind='neutral'): string{return '<span class="pill pill-'.h($kind).'">'.h($text).'</span>';}
function form_start(string $action,string $back='dashboard'): void {echo '<form method="post" action="/">'.csrf_field().'<input type="hidden" name="action" value="'.h($action).'"><input type="hidden" name="back" value="'.h($back).'">';}
function layout_start(string $page): void {global $db; $labels=['dashboard'=>'Tổng quan','projects'=>'Dự án','features'=>'Tính năng & môi trường','preview'=>'Web mẫu (Trải nghiệm)','submit'=>'Gửi tính năng mới','groups'=>'Nhóm thử nghiệm','versions'=>'Phiên bản & khôi phục','api_info'=>'API & Web mẫu','approvals'=>'Phê duyệt','audit'=>'Nhật ký','accounts'=>'Tài khoản'];$title=$labels[$page]??'FeatureFlag Manager';$pending=count(array_filter($db['requests'],fn($r)=>$r['status']==='pending'));echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' · FeatureFlag Manager</title><link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js" defer></script></head><body><div class="shell"><aside class="sidebar"><a class="brand" href="/?p=dashboard"><span class="brand-icon">✦</span><span>Flag<span class="brand-light">Flow</span><small>FEATURE OPERATIONS</small></span></a><div class="nav-label">WORKSPACE</div><nav>';
  foreach(['dashboard'=>'▦','projects'=>'▤','features'=>'◈','preview'=>'▣','submit'=>'➤','groups'=>'♧','versions'=>'⧉','api_info'=>'⌘','approvals'=>'◉','audit'=>'◷','accounts'=>'♙'] as $name=>$icon){
    if(in_array($name,['audit','accounts'],true)&&!is_admin())continue;
    if(in_array($name,['submit','groups','versions','api_info','approvals'],true)&&!can_edit())continue;
    echo '<a class="nav-link '.($page===$name?'active':'').'" href="/?p='.$name.'"><span class="nav-icon">'.$icon.'</span>'.h($labels[$name]).($name==='approvals'&&$pending?'<em>'.$pending.'</em>':'').'</a>';
  }
  echo '</nav><div class="sidebar-bottom"><div class="avatar">'.h(mb_substr(user()['name'],0,1)).'</div><div class="sidebar-user"><b>'.h(user()['name']).'</b><small>'.h(strtoupper(user()['role'])).'</small></div>';form_start('logout');echo '<button class="logout" title="Đăng xuất">⇥</button></form></div></aside><main class="main"><header class="topbar"><div><span class="breadcrumb">WORKSPACE / COMMUNITYPASS</span><h1>'.h($title).'</h1></div><div class="header-meta"><span class="live-dot"></span> Demo nội bộ <span class="top-avatar">'.h(mb_substr(user()['name'],0,1)).'</span></div></header><div class="content">';
  if(isset($_SESSION['flash'])){$f=$_SESSION['flash'];unset($_SESSION['flash']);echo '<div class="flash '.($f['type']==='error'?'flash-error':'').'">'.h($f['message']).'</div>';}
}
function layout_end(): void {echo '</div><footer>FeatureFlag Manager · PHP 8.1+ · CommunityPass · Hệ thống demo cho bài tập lớn</footer></main></div></body></html>';}
if($page==='login') { $flash=$_SESSION['flash']??null;unset($_SESSION['flash']);echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đăng nhập · FlagFlow</title><link rel="stylesheet" href="/assets/app.css"></head><body class="login-body"><div class="login-card"><div class="login-symbol">✦</div><div class="eyebrow">FEATURE OPERATIONS PLATFORM</div><h1>Chào mừng trở lại</h1><p>Đăng nhập để quản lý phát hành tính năng và các nhóm thử nghiệm.</p>'.($flash?'<div class="flash flash-error">'.h($flash['message']).'</div>':'');form_start('login','login');echo '<label>Tài khoản<input name="username" autocomplete="username" required placeholder="admin / dev / tester"></label><label>Mật khẩu<input name="password" type="password" autocomplete="current-password" required placeholder="Nhập mật khẩu"></label><button class="btn primary full">Đăng nhập →</button></form><div class="login-note">Chưa có tài khoản? <a class="auth-link" href="/?p=register">Đăng ký tài khoản (Tester / Developer) →</a><br>Hệ thống có 1 Quản trị viên (Admin) duy nhất: <code>admin</code></div></div></body></html>';exit;}
if($page==='register') {
  $flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
  echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đăng ký tài khoản · FlagFlow</title><link rel="stylesheet" href="/assets/app.css"></head><body class="login-body"><div class="login-card"><div class="login-symbol">✦</div><div class="eyebrow">FEATURE OPERATIONS PLATFORM</div><h1>Đăng ký tài khoản</h1><p>Đăng ký tài khoản Tester để thử nghiệm tính năng, hoặc nhập mã nội bộ để đăng ký làm Developer.</p>'.($flash?'<div class="flash flash-error">'.h($flash['message']).'</div>':'');
  form_start('register','register');
  echo '<label>Họ và tên<input name="name" minlength="2" maxlength="80" required autocomplete="name" placeholder="Ví dụ: Nguyễn Văn A"></label>';
  echo '<label>Tên đăng nhập (3–30 ký tự, chữ thường, số, dấu _)<input name="username" pattern="[a-z][a-z0-9_]{2,29}" minlength="3" maxlength="30" required autocomplete="username" placeholder="Ví dụ: my_account"></label>';
  echo '<label>Vai trò<select name="role" id="role_select" onchange="document.getElementById(\'dev_code_box\').style.display=this.value===\'developer\'?\'block\':\'none\';"><option value="tester" selected>Tester (Người thử nghiệm - tự do đăng ký)</option><option value="developer">Developer (Lập trình viên - yêu cầu mã nội bộ)</option></select></label>';
  echo '<div id="dev_code_box" style="display:none;"><label>Mã kích hoạt Developer <small style="color:#6366f1;font-weight:normal">(Mã nội bộ: DEV2026)</small><input name="dev_code" placeholder="Nhập mã xác thực Developer: DEV2026"></label></div>';
  echo '<label>Mật khẩu (tối thiểu 10 ký tự)<input name="password" type="password" minlength="10" maxlength="128" required autocomplete="new-password" placeholder="Nhập mật khẩu (từ 10 ký tự)"></label>';
  echo '<label>Nhập lại mật khẩu<input name="confirm_password" type="password" minlength="10" maxlength="128" required autocomplete="new-password" placeholder="Nhập lại mật khẩu"></label>';
  echo '<button class="btn primary full">Đăng ký tài khoản →</button></form><div class="login-note">Đã có tài khoản? <a class="auth-link" href="/?p=login">Đăng nhập ngay</a><br>Quản trị viên (Admin) chỉ có 1 tài khoản duy nhất do chủ hệ thống quản lý.</div></div></body></html>';exit;
}
layout_start($page);
if($page==='dashboard'){
  $enabled=count(array_filter($db['configs'],fn($c)=>$c['enabled']));$pending=count(array_filter($db['requests'],fn($r)=>$r['status']==='pending'));
  if(!can_edit()){
    echo '<section class="hero"><div class="eyebrow">TESTER PORTAL</div><h2>Trải nghiệm tính năng<br><span>theo nhóm thử nghiệm.</span></h2><p>Bạn đang đăng nhập với vai trò <b>Tester (Người thử nghiệm)</b>. Hãy mở Web mẫu để kiểm tra các tính năng được mở riêng cho tài khoản của bạn.</p><a class="btn white" href="/?p=preview">Mở Web mẫu CommunityPass →</a><div class="hero-art"><span>TEST</span><i>→</i><span>BETA</span><i>→</i><span>LIVE</span></div></section>';
    echo '<div class="stats" style="grid-template-columns: repeat(3, 1fr);"><div class="stat"><span>Dự án</span><strong>'.count($db['projects']).'</strong><small>Được cấp quyền xem</small></div><div class="stat"><span>Tính năng</span><strong>'.count($db['features']).'</strong><small>Các feature flags</small></div><div class="stat"><span>Vai trò của bạn</span><strong style="color:#10b981; font-size:24px;">TESTER</strong><small>Quyền xem & Trải nghiệm mẫu</small></div></div>';
  } else {
    echo '<section class="hero"><div class="eyebrow">RELEASE CONTROL CENTER</div><h2>Phát hành tính năng<br><span>có kiểm soát.</span></h2><p>Thử nghiệm trong Development, cho nhóm được chọn dùng ở Beta và chỉ đưa lên Production sau khi được phê duyệt.</p><a class="btn white" href="/?p=features">Quản lý tính năng →</a><div class="hero-art"><span>DEV</span><i>→</i><span>BETA</span><i>→</i><span>PROD</span></div></section>';
    echo '<div class="stats"><div class="stat"><span>Dự án</span><strong>'.count($db['projects']).'</strong><small>Không gian làm việc</small></div><div class="stat"><span>Tính năng</span><strong>'.count($db['features']).'</strong><small>Các feature flags</small></div><div class="stat"><span>Cấu hình đang bật</span><strong>'.$enabled.'</strong><small>Trên cả 3 môi trường</small></div><div class="stat"><span>Chờ phê duyệt</span><strong>'.$pending.'</strong><small>Yêu cầu Beta / Production</small></div></div>';
  }
  echo '<div class="two-col"><section class="panel"><div class="section-head"><div><h3>Quy trình phát hành</h3><p>Đẩy tính năng theo từng giai đoạn</p></div></div><div class="pipeline"><div><b>01</b><span>Development<small>Developer bật/tắt để kiểm thử</small></span>'.pill('Trực tiếp','blue').'</div><div><b>02</b><span>Beta<small>Gửi Admin duyệt, giới hạn theo nhóm</small></span>'.pill('Phê duyệt','orange').'</div><div><b>03</b><span>Production<small>Admin duyệt trước khi áp dụng</small></span>'.pill('Phê duyệt','violet').'</div></div></section><section class="panel"><div class="section-head"><div><h3>Hoạt động gần đây</h3><p>Truy vết thay đổi cấu hình</p></div>'.(is_admin()?'<a href="/?p=audit">Xem tất cả →</a>':'').'</div><div class="activity">';foreach(array_slice(array_reverse($db['audit']),0,6) as $log)echo '<div><span class="activity-mark"></span><p><b>'.h($log['action']).'</b><small>'.h($log['detail']).'</small></p><time>'.h(substr($log['at'],0,16)).'</time></div>';if(!$db['audit'])echo '<p class="empty">Chưa có hoạt động. Hãy tạo thay đổi đầu tiên.</p>';echo '</div></section></div>';
}elseif($page==='accounts' && is_admin()){
  echo '<div class="section-head"><div><h2>Quản lý tài khoản</h2><p>Admin cấp quyền nội bộ, người đăng ký công khai chỉ có vai trò Tester.</p></div>'.pill(count($db['users']).' tài khoản','blue').'</div>';
  echo '<section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Tài khoản</th><th>Họ tên</th><th>Vai trò</th><th>Đặt lại mật khẩu</th></tr></thead><tbody>';
  foreach($db['users'] as $u){echo '<tr><td><b>'.h($u['username']).'</b></td><td>'.h($u['name']).'</td><td>'.pill($u['role'],$u['role']==='admin'?'violet':($u['role']==='developer'?'blue':'green')).'</td><td>';form_start('account_password','accounts');echo '<input type="hidden" name="user_id" value="'.(int)$u['id'].'"><div class="account-reset"><input type="password" name="password" required minlength="10" maxlength="128" autocomplete="new-password" placeholder="Mật khẩu mới"><input type="password" name="confirm_password" required minlength="10" maxlength="128" autocomplete="new-password" placeholder="Nhập lại"><button class="btn secondary small">Đổi mật khẩu</button></div></form></td></tr>';}
  echo '</tbody></table></div></section>';
  echo '<section class="panel form-panel"><h3>+ Tạo tài khoản mới</h3><p class="muted">Chỉ Admin có thể tạo Developer hoặc Admin mới. Không gửi mật khẩu qua GitHub.</p>';
  form_start('account_create','accounts');echo '<div class="form-grid"><label>Họ tên<input name="name" required minlength="2" maxlength="80" placeholder="Nguyễn Văn A"></label><label>Tên đăng nhập<input name="username" required pattern="[a-z][a-z0-9_]{2,29}" placeholder="tester_moi"></label><label>Vai trò<select name="role"><option value="tester">Tester</option><option value="developer">Developer</option><option value="admin">Admin</option></select></label><label>Mật khẩu mới<input name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password"></label><label>Xác nhận mật khẩu<input name="confirm_password" type="password" required minlength="10" maxlength="128" autocomplete="new-password"></label></div><button class="btn primary">+ Tạo tài khoản</button></form></section>';
}elseif($page==='projects'){
  echo '<div class="section-head"><div><h2>Quản lý dự án & Thành viên</h2><p>Phân tách quyền xem (Viewer) và quyền sửa (Editor / Manager); cấu hình độc lập cho môi trường Thử nghiệm (Dev / Beta) và Vận hành (Production).</p></div>'.pill(count($db['projects']).' dự án','blue').'</div>';

  echo '<div class="stats" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 24px;">';
  echo '<div class="stat"><span style="color:#6366f1; font-weight:700">👑 QUẢN TRỊ VIÊN (ADMIN)</span><strong>Toàn quyền</strong><small>Tạo dự án, phân quyền thành viên, duyệt & phát hành bản Production.</small></div>';
  echo '<div class="stat"><span style="color:#0ea5e9; font-weight:700">✏️ QUYỀN SỬA (EDITOR)</span><strong>Xem & Sửa nháp</strong><small>Bật/tắt tính năng ở Development, tạo nháp phiên bản, gửi yêu cầu duyệt.</small></div>';
  echo '<div class="stat"><span style="color:#10b981; font-weight:700">👁️ QUYỀN XEM (VIEWER)</span><strong>Chỉ đọc (Read-only)</strong><small>Xem danh sách tính năng và môi trường; không được phép sửa hay gửi duyệt.</small></div>';
  echo '</div>';

  echo '<div class="project-grid">';
  foreach($db['projects'] as $p){
    if(!ProjectAccess::canView($db,user(),(int)$p['id']))continue;
    $userLevel = is_admin() ? 'manager' : ProjectAccess::level($db, user(), (int)$p['id']);
    $levelLabel = match($userLevel) {
      'manager' => '👑 Manager (Toàn quyền)',
      'editor' => '✏️ Editor (Quyền sửa)',
      'viewer' => '👁️ Viewer (Chỉ xem)',
      default => 'Chưa cấp quyền'
    };
    $levelColor = match($userLevel) {
      'manager' => 'violet',
      'editor' => 'blue',
      default => 'neutral'
    };
    $projFeatures = array_filter($db['features'], fn($f)=>$f['project_id']===$p['id']);
    $featIds = array_column($projFeatures, 'id');
    $devEnabled = count(array_filter($db['configs']??[], fn($c)=>in_array($c['feature_id'], $featIds, true) && $c['environment']==='development' && $c['enabled']));
    $betaEnabled = count(array_filter($db['configs']??[], fn($c)=>in_array($c['feature_id'], $featIds, true) && $c['environment']==='beta' && $c['enabled']));
    $prodEnabled = count(array_filter($db['configs']??[], fn($c)=>in_array($c['feature_id'], $featIds, true) && $c['environment']==='production' && $c['enabled']));
    $memberCount = count(array_filter($db['project_memberships']??[], fn($m)=>(int)$m['project_id']===(int)$p['id']));

    echo '<article class="project-card" style="display:flex; flex-direction:column; justify-content:space-between;">';
    echo '<div>';
    echo '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">';
    echo '<span class="project-logo">◈</span>';
    echo pill($levelLabel, $levelColor);
    echo '</div>';
    echo '<h3>'.h($p['name']).'</h3>';
    echo '<code style="display:inline-block; margin-bottom:8px;">'.h($p['key']).'</code>';
    echo '<p>'.h($p['description']).'</p>';
    echo '<div style="background:#f8faff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin:14px 0; font-size:11px;">';
    echo '<div style="font-weight:700; color:#475569; margin-bottom:6px;">CẤU HÌNH THEO MÔI TRƯỜNG:</div>';
    echo '<div style="display:flex; justify-content:space-between; margin-bottom:3px;"><span>🧪 Dev (Thử nghiệm):</span><b>'.$devEnabled.' bật</b></div>';
    echo '<div style="display:flex; justify-content:space-between; margin-bottom:3px;"><span>👥 Beta (Nhóm test):</span><b>'.$betaEnabled.' bật</b></div>';
    echo '<div style="display:flex; justify-content:space-between;"><span>🚀 Prod (Vận hành):</span><b>'.$prodEnabled.' bật</b></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="project-foot">';
    echo '<span>'.count($projFeatures).' tính năng · '.$memberCount.' thành viên</span>';
    echo '<div style="display:flex; gap:10px;">';
    echo '<a href="/?p=features" style="color:#6366f1; font-weight:700;">Môi trường →</a>';
    echo '<a href="/?p=versions" style="color:#0ea5e9; font-weight:700;">Phiên bản →</a>';
    echo '</div>';
    echo '</div>';
    echo '</article>';
  }
  echo '</div>';

  echo '<section class="panel table-panel" style="margin-top:24px;">';
  echo '<div class="section-head" style="padding: 20px 20px 0;"><div><h3>Thành viên & Phân quyền dự án</h3><p>Danh sách tài khoản được cấp quyền truy cập và chỉnh sửa trong từng dự án.</p></div></div>';
  echo '<div class="table-wrap"><table><thead><tr><th>Dự án</th><th>Tài khoản</th><th>Họ tên</th><th>Vai trò hệ thống</th><th>Quyền trong dự án</th><th>Thao tác</th></tr></thead><tbody>';
  $memberships = $db['project_memberships'] ?? [];
  if(empty($memberships)) {
    echo '<tr><td colspan="6" class="empty">Chưa có thành viên nào được phân quyền riêng theo dự án.</td></tr>';
  } else {
    foreach($memberships as $m) {
      $proj = Policy::find($db['projects'], (int)$m['project_id']);
      $u = Policy::find($db['users'], (int)$m['user_id']);
      if(!$proj || !$u) continue;
      if(!is_admin() && !ProjectAccess::canView($db, user(), (int)$proj['id'])) continue;
      $permBadge = match($m['permission']) {
        'manager' => pill('Manager · Quản lý dự án', 'violet'),
        'editor' => pill('Editor · Xem & sửa nháp', 'blue'),
        'viewer' => pill('Viewer · Chỉ xem', 'neutral'),
        default => pill($m['permission'], 'neutral')
      };
      echo '<tr>';
      echo '<td><b>'.h($proj['name']).'</b><br><code>'.h($proj['key']).'</code></td>';
      echo '<td><b>'.h($u['username']).'</b></td>';
      echo '<td>'.h($u['name']).'</td>';
      echo '<td>'.pill($u['role'], $u['role']==='admin'?'violet':($u['role']==='developer'?'blue':'green')).'</td>';
      echo '<td>'.$permBadge.'</td>';
      echo '<td>';
      if(is_admin()) {
        form_start('project_member_revoke', 'projects');
        echo '<input type="hidden" name="project_id" value="'.(int)$proj['id'].'">';
        echo '<input type="hidden" name="user_id" value="'.(int)$u['id'].'">';
        echo '<button class="text-btn" onclick="return confirm(\'Xác nhận gỡ thành viên khỏi dự án?\');">✕ Gỡ quyền</button>';
        echo '</form>';
      } else {
        echo '<span class="muted" style="font-size:11px;">Chỉ Admin được sửa</span>';
      }
      echo '</td>';
      echo '</tr>';
    }
  }
  echo '</tbody></table></div></section>';

  if(is_admin()){
    echo '<section class="panel form-panel"><h3>+ Cấp / Điều chỉnh quyền thành viên theo dự án</h3><p class="muted">Phân biệt rõ: <b>Viewer</b> (chỉ được xem, không thể sửa), <b>Editor</b> (được tạo nháp, bật tắt môi trường dev), <b>Manager</b> (quản lý dự án).</p>';
    form_start('project_member_grant','projects');
    echo '<div class="form-grid">';
    echo '<label>Dự án<select name="project_id" required>';
    foreach($db['projects'] as $p) echo '<option value="'.$p['id'].'">'.h($p['name']).' ('.h($p['key']).')</option>';
    echo '</select></label>';
    echo '<label>Tài khoản thành viên<select name="user_id" required>';
    foreach($db['users'] as $u) echo '<option value="'.$u['id'].'">'.h($u['name']).' (@'.h($u['username']).' - '.h($u['role']).')</option>';
    echo '</select></label>';
    echo '<label>Quyền trong dự án<select name="permission" required><option value="viewer">Viewer (Chỉ xem cấu hình, không được sửa)</option><option value="editor" selected>Editor (Xem & sửa nháp, bật/tắt Development)</option><option value="manager">Manager (Quản lý dự án toàn diện)</option></select></label>';
    echo '</div><button class="btn primary">Lưu phân quyền thành viên</button></form></section>';
  }

  if(can_edit()){
    echo '<section class="panel form-panel"><h3>+ Thêm dự án mới</h3><p class="muted">Mỗi dự án quản lý một không gian độc lập với 3 môi trường: Development, Beta, Production.</p>';
    form_start('project_create','projects');
    echo '<div class="form-grid"><label>Tên dự án<input required maxlength="80" name="name" placeholder="Ví dụ: Event Portal"></label><label>Mã dự án (key)<input required pattern="[a-z][a-z0-9_]{2,39}" name="key" placeholder="event_portal"></label></div><label>Mô tả dự án<input name="description" maxlength="300" placeholder="Mô tả mục tiêu của dự án"></label><button class="btn primary">+ Tạo dự án</button></form></section>';
  } else {
    echo '<div class="panel" style="background:#f8fafc; border:1px solid #e2e8f0; color:#64748b; font-size:12px; margin-top:20px;"><b>ℹ️ Thông báo phân quyền:</b> Tài khoản hiện tại có vai trò <b>Tester (Viewer)</b>. Bạn chỉ có quyền xem cấu hình dự án và trạng thái tính năng, không thể tạo thêm dự án mới hoặc thay đổi quyền thành viên.</div>';
  }
}elseif($page==='submit' && can_edit()){
  echo '<div class="section-head"><div><h2>Gửi tính năng mới cho Admin</h2><p>Tạo tính năng ở Development và gửi yêu cầu phát hành Beta/Production trong một lần.</p></div>'.pill('Cần phê duyệt','orange').'</div>';
  echo '<section class="panel form-panel"><h3>Thông tin tính năng</h3>';
  form_start('feature_submit','submit');
  echo '<div class="form-grid"><label>Dự án<select name="project_id" required>';
  foreach($db['projects'] as $project)echo '<option value="'.(int)$project['id'].'">'.h($project['name']).'</option>';
  echo '</select></label><label>Mã tính năng<input name="key" required pattern="[a-z][a-z0-9_]{2,39}" placeholder="new_character"></label><label>Tên tính năng<input name="name" required minlength="3" maxlength="90" placeholder="Tướng mới"></label><label>Môi trường gửi duyệt<select name="environment"><option value="beta">Beta – nhóm thử nghiệm</option><option value="production">Production – chính thức</option></select></label></div>';
  echo '<label>Mô tả tính năng<textarea name="description" maxlength="300" rows="3" placeholder="Tính năng làm gì? Dùng để làm gì?"></textarea></label>';
  echo '<label>Nhóm được phép dùng thử (chỉ chọn nhóm cùng dự án)<select name="group_id"><option value="">Tất cả người dùng sau khi duyệt</option>';
  foreach($db['groups'] as $g)echo '<option value="'.(int)$g['id'].'">'.h(name_of($db['projects'],$g['project_id'])).' / '.h($g['name']).'</option>';
  echo '</select></label><label>Lý do gửi duyệt<textarea name="reason" required minlength="5" maxlength="500" rows="3" placeholder="Đã kiểm thử trong Development; đề nghị Admin duyệt cho nhóm Beta..."></textarea></label>';
  echo '<p class="hint">Sau khi gửi: Development được bật để kiểm thử nội bộ. Beta/Production vẫn tắt cho đến khi một Admin khác phê duyệt. Nhóm phải thuộc dự án đã chọn.</p><button class="btn primary">Gửi yêu cầu cho Admin →</button></form></section>';
}elseif($page==='features'){
  echo '<div class="section-head"><div><h2>Feature flags & Cấu hình môi trường</h2><p>Development áp dụng ngay để thử nghiệm mô phỏng. Beta và Production phải gửi yêu cầu phê duyệt.</p></div>'.pill('3 môi trường','blue').'</div>';
  foreach($db['projects'] as $project){
    if(!ProjectAccess::canView($db,user(),(int)$project['id']))continue;
    $canEditProj = ProjectAccess::canEdit($db,user(),(int)$project['id']);
    echo '<div style="display:flex; justify-content:space-between; align-items:center; margin: 24px 0 14px;">';
    echo '<h3 class="group-heading" style="margin:0;">'.h($project['name']).' <small>'.h($project['key']).'</small></h3>';
    echo pill($canEditProj ? '✏️ Quyền sửa (Editor)' : '👁️ Chỉ xem (Viewer)', $canEditProj ? 'blue' : 'neutral');
    echo '</div>';
    foreach($db['features'] as $f){
      if($f['project_id']!==$project['id'])continue;
      echo '<article class="flag-card"><div class="flag-head"><div class="flag-logo">⚑</div><div><h3>'.h($f['name']).'</h3><code>'.h($f['key']).'</code><p>'.h($f['description']).'</p></div></div><div class="env-grid">';
      foreach(Policy::ENVS as $env){
        $c=config_of($db,$f['id'],$env);
        echo '<div class="env-card"><div class="env-title"><b>'.h($envLabels[$env]).'</b>'.pill($c&&$c['enabled']?'Đang bật':'Đang tắt',$c&&$c['enabled']?'green':'neutral').'</div><p>'.($c&&$c['group_id']!==null?'Nhóm: '.h(name_of($db['groups'],$c['group_id'])):'Đối tượng: mọi người dùng').'</p>';
        if($canEditProj){
          form_start('config_change','features');
          echo '<input type="hidden" name="feature_id" value="'.$f['id'].'"><input type="hidden" name="environment" value="'.h($env).'"><div class="form-grid compact"><label>Trạng thái<select name="enabled"><option value="1" '.($c&&$c['enabled']?'selected':'').'>Bật</option><option value="0" '.(!$c||!$c['enabled']?'selected':'').'>Tắt</option></select></label><label>Nhóm thử nghiệm<select name="group_id"><option value="">Tất cả</option>';
          foreach($db['groups'] as $g)if($g['project_id']===$project['id'])echo '<option value="'.$g['id'].'" '.($c&&$c['group_id']===$g['id']?'selected':'').'>'.h($g['name']).'</option>';
          echo '</select></label></div>';
          if($env!=='development')echo '<label>Lý do yêu cầu<input name="reason" maxlength="500" placeholder="Lý do gửi Admin duyệt" required minlength="5"></label>';
          echo '<button class="btn '.($env==='development'?'secondary':'primary').' small">'.($env==='development'?'Áp dụng ngay':'Gửi duyệt').' →</button></form>';
        } else {
          echo '<div style="font-size:11px; color:#64748b; background:#f1f5f9; padding:8px 10px; border-radius:6px; margin-top:8px;">🔒 Chế độ chỉ xem: Cần quyền Editor để sửa cấu hình.</div>';
        }
        echo '</div>';
      }
      echo '</div></article>';
    }
  }
  if(can_edit()){echo '<section class="panel form-panel"><h3>+ Tạo tính năng mới</h3>';form_start('feature_create','features');echo '<div class="form-grid"><label>Dự án<select name="project_id">';foreach($db['projects'] as $p)if(ProjectAccess::canEdit($db,user(),(int)$p['id']))echo '<option value="'.$p['id'].'">'.h($p['name']).'</option>';echo '</select></label><label>Mã tính năng<input required pattern="[a-z][a-z0-9_]{2,39}" name="key" placeholder="new_checkout"></label><label>Tên tính năng<input required maxlength="90" name="name" placeholder="Thanh toán mới"></label><label>Mô tả<input maxlength="300" name="description" placeholder="Mô tả ngắn"></label></div><button class="btn primary">Tạo tính năng</button></form></section>';}
}elseif($page==='versions'){
  echo '<div class="section-head"><div><h2>Quản lý phiên bản & Thứ tự ưu tiên cấu hình</h2><p>Quy định thứ tự ưu tiên khi nhiều điều kiện cùng đúng: <b>Tắt toàn cục → Danh sách chặn → Danh sách cho phép → Nhóm thử nghiệm → Tỷ lệ % (SHA-256)</b>.</p></div>'.pill('BTL · Phiên bản & Thuật toán ưu tiên','blue').'</div>';

  echo '<section class="panel" style="background:#fff; border-left: 5px solid #6366f1; margin-bottom: 24px;">';
  echo '<h3 style="margin-top:0;">⚡ Thứ tự ưu tiên giải quyết khi nhiều điều kiện cùng thỏa mãn</h3>';
  echo '<p class="muted">Động cơ đánh giá cờ tính năng (<code>FlagEngine</code>) tuân thủ nghiêm ngặt quy trình 5 bước theo thứ tự giảm dần dưới đây:</p>';
  echo '<div class="pipeline" style="grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top:16px;">';
  echo '<div><b>01</b><span>Tắt toàn cục<small>Bật=Tắt ➔ Dừng ngay, cờ TẮT</small></span>'.pill('Mức 1 (Cao nhất)','red').'</div>';
  echo '<div><b>02</b><span>Danh sách chặn<small>User thuộc Deny list ➔ TẮT ngay</small></span>'.pill('Mức 2','orange').'</div>';
  echo '<div><b>03</b><span>Danh sách cho phép<small>User thuộc Allow list ➔ BẬT ngay</small></span>'.pill('Mức 3','green').'</div>';
  echo '<div><b>04</b><span>Điều kiện nhóm<small>Phải thuộc nhóm Cohort chỉ định</small></span>'.pill('Mức 4','blue').'</div>';
  echo '<div><b>05</b><span>Tỷ lệ % (0–100%)<small>Băm SHA-256 cố định deterministic</small></span>'.pill('Mức 5','violet').'</div>';
  echo '</div>';
  echo '</section>';

  // --- TRÌNH ĐÁNH GIÁ & MÔ PHỎNG THỨ TỰ ƯU TIÊN (LIVE SIMULATOR) ---
  $simVid = (int)($_GET['sim_vid'] ?? ($_GET['id'] ?? ($versions[0]['id'] ?? 0)));
  $simVersion = $simVid > 0 ? Policy::find($db['versions'], $simVid) : null;
  $simFeatures = $simVersion ? array_values(array_filter($db['features'], fn($f)=>$f['project_id']===$simVersion['project_id'])) : [];
  $simFid = (int)($_GET['sim_fid'] ?? ($simFeatures[0]['id'] ?? 0));
  $simUser = trim((string)($_GET['sim_user'] ?? ''));

  echo '<section class="panel form-panel" style="background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%); border: 1px solid #d9e2ec; margin-bottom:24px;">';
  echo '<h3>🧪 Trình kiểm tra & Mô phỏng quyết định tính năng (Flag Decision Evaluator)</h3>';
  echo '<p class="muted">Nhập mã người dùng bất kỳ để kiểm chứng thứ tự ưu tiên xử lý qua thuật toán <code>FlagEngine::evaluate()</code>.</p>';
  echo '<form method="get" action="/"><input type="hidden" name="p" value="versions">';
  echo '<div class="form-grid">';
  echo '<label>Phiên bản kiểm tra<select name="sim_vid" onchange="this.form.submit()">';
  foreach($versions as $ver) {
    echo '<option value="'.$ver['id'].'" '.($ver['id']===$simVid?'selected':'').'>v'.(int)$ver['number'].' (#'.$ver['id'].') · '.h(name_of($db['projects'],$ver['project_id'])).' · '.h($ver['environment']).' ('.h($ver['status']).')</option>';
  }
  echo '</select></label>';
  echo '<label>Tính năng cần kiểm tra<select name="sim_fid">';
  foreach($simFeatures as $sf) {
    echo '<option value="'.$sf['id'].'" '.($sf['id']===$simFid?'selected':'').'>'.h($sf['name']).' ('.h($sf['key']).')</option>';
  }
  echo '</select></label>';
  echo '</div>';
  echo '<label>Mã người dùng (User Code)<input name="sim_user" value="'.h($simUser).'" required placeholder="Ví dụ: user0001, user0002, tester..."></label>';
  echo '<div style="margin: -8px 0 14px; font-size:11px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
  echo '<span style="color:#64748b;">Gợi ý mã thử nghiệm mẫu:</span>';
  echo '<a href="/?p=versions&sim_vid='.$simVid.'&sim_fid='.$simFid.'&sim_user=user0001" style="background:#e0e7ff; color:#4338ca; padding:3px 8px; border-radius:6px; font-weight:600;">user0001</a>';
  echo '<a href="/?p=versions&sim_vid='.$simVid.'&sim_fid='.$simFid.'&sim_user=user0002" style="background:#e0e7ff; color:#4338ca; padding:3px 8px; border-radius:6px; font-weight:600;">user0002</a>';
  echo '<a href="/?p=versions&sim_vid='.$simVid.'&sim_fid='.$simFid.'&sim_user=user0015" style="background:#e0e7ff; color:#4338ca; padding:3px 8px; border-radius:6px; font-weight:600;">user0015</a>';
  echo '<a href="/?p=versions&sim_vid='.$simVid.'&sim_fid='.$simFid.'&sim_user=tester" style="background:#e0e7ff; color:#4338ca; padding:3px 8px; border-radius:6px; font-weight:600;">tester</a>';
  echo '</div>';
  echo '<button class="btn primary">🔍 Đánh giá quyết định</button>';
  echo '</form>';

  if($simUser !== '' && $simVersion && $simFid > 0) {
    $evalResult = FlagEngine::evaluate($db, $simVersion, $simFid, $simUser);
    $feat = Policy::find($db['features'], $simFid);
    $rule = null;
    foreach($simVersion['flags'] as $fl) {
      if((int)$fl['feature_id'] === $simFid) { $rule = $fl; break; }
    }
    
    $reasonDesc = match($evalResult['reason']) {
      'disabled' => '🛑 <b>Mức 1 - Tắt toàn cục:</b> Tính năng đang ở trạng thái TẮT. Toàn bộ các điều kiện ưu tiên tiếp theo bị bỏ qua.',
      'deny-list' => '⛔ <b>Mức 2 - Danh sách chặn (Deny List):</b> Mã người dùng <code>'.h($simUser).'</code> nằm trong danh sách chặn. Quy tắc chặn luôn thắng điều kiện nhóm và tỷ lệ phần trăm.',
      'allow-list' => '✅ <b>Mức 3 - Danh sách cho phép (Allow List):</b> Mã người dùng <code>'.h($simUser).'</code> nằm trong danh sách cho phép đặc quyền. Bật ngay lập tức, bỏ qua điều kiện nhóm và tỷ lệ %!',
      'outside-cohort' => '👥 <b>Mức 4 - Điều kiện nhóm thử nghiệm (Cohort Gate):</b> Tính năng chỉ mở cho nhóm <b>'.h(name_of($db['groups'], $rule['group_id']??0)).'</b>. Người dùng <code>'.h($simUser).'</code> không thuộc nhóm này nên bị TẮT.',
      'rollout-0' => '📊 <b>Mức 5 - Tỷ lệ sử dụng (0%):</b> Tỷ lệ rollout đặt là 0% nên tính năng TẮT với mọi người dùng thông thường.',
      'rollout-100' => '🎉 <b>Mức 5 - Tỷ lệ sử dụng (100%):</b> Tỷ lệ rollout đặt là 100% nên tính năng BẬT với toàn bộ người dùng thỏa mãn nhóm.',
      'percentage' => '🎲 <b>Mức 5 - Tỷ lệ sử dụng (SHA-256 Hash):</b> Điểm băm SHA-256 là <b>'.$evalResult['bucket'].'</b> / 10000. Ngưỡng yêu cầu: < <b>'.((int)($rule['percentage']??0) * 100).'</b> ('.(int)($rule['percentage']??0).'%). Kết quả: '.($evalResult['enabled']?'BẬT':'TẮT').' (cố định, không đổi khi F5).',
      default => h($evalResult['reason'])
    };

    echo '<div style="margin-top:20px; padding:18px; border-radius:12px; background:'.($evalResult['enabled']?'#ecfdf5; border:1px solid #a7f3d0;':'#fef2f2; border:1px solid #fecaca;').'">';
    echo '<div style="display:flex; justify-content:space-between; align-items:center;">';
    echo '<div>';
    echo '<span style="font-size:11px; font-weight:800; letter-spacing:1px; color:'.($evalResult['enabled']?'#065f46;':'#991b1b;').'">KẾT QUẢ QUYẾT ĐỊNH CHO TÍNH NĂNG "'.h($feat['name']??'').'"</span>';
    echo '<h3 style="margin:4px 0 0; color:'.($evalResult['enabled']?'#047857;':'#b91c1c;').'">'.($evalResult['enabled']?'✨ BẬT (ENABLED)':'🔒 TẮT (DISABLED)').'</h3>';
    echo '</div>';
    echo pill($evalResult['enabled']?'BẬT':'TẮT', $evalResult['enabled']?'green':'red');
    echo '</div>';
    echo '<p style="margin:12px 0 0; font-size:13px; line-height:1.6; color:#1e293b;">'.$reasonDesc.'</p>';
    echo '</div>';
  }
  echo '</section>';

  if(can_edit()){
    echo '<section class="panel form-panel"><h3>Tạo bản nháp từ phiên bản đang hoạt động</h3>';form_start('version_draft_create','versions');
    echo '<div class="form-grid"><label>Dự án<select name="project_id">';foreach($db['projects'] as $project)if(ProjectAccess::canEdit($db,user(),(int)$project['id']))echo '<option value="'.$project['id'].'">'.h($project['name']).'</option>';
    echo '</select></label><label>Môi trường<select name="environment">';foreach(Policy::ENVS as $env)echo '<option value="'.$env.'">'.h($envLabels[$env]).'</option>';
    echo '</select></label><label>Lý do tạo nháp<input name="reason" maxlength="500" value="Thử nghiệm tỷ lệ bật tính năng"></label></div><button class="btn primary">+ Tạo bản nháp</button></form></section>';
    echo '<section class="panel form-panel"><h3>Nhập cấu hình JSON thành nháp</h3><form action="/" method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="version_import"><input type="hidden" name="back" value="versions"><div class="form-grid"><label>Dự án<select name="project_id">';foreach($db['projects'] as $p)if(ProjectAccess::canEdit($db,user(),(int)$p['id']))echo '<option value="'.$p['id'].'">'.h($p['name']).'</option>';
    echo '</select></label><label>Môi trường<select name="environment">';foreach(Policy::ENVS as $env)echo '<option value="'.$env.'">'.h($env).'</option>';echo '</select></label><label>Tệp JSON (tối đa 150 KB)<input type="file" name="configuration" accept="application/json,.json" required></label></div><button class="btn secondary">Kiểm tra và nhập thành nháp</button></form></section>';
  }
  $versions=array_values(array_filter(array_reverse($db['versions']??[]),fn($v)=>ProjectAccess::canView($db,user(),(int)$v['project_id'])));
  echo '<section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Phiên bản</th><th>Dự án</th><th>Môi trường</th><th>Trạng thái</th><th>Chọn</th><th>Xuất</th></tr></thead><tbody>';
  foreach($versions as $v){$active=($db['active_versions'][$v['project_id'].':'.$v['environment']]??0)===$v['id'];echo '<tr><td><b>v'.(int)$v['number'].'</b> · #'.(int)$v['id'].'</td><td>'.h(name_of($db['projects'],$v['project_id'])).'</td><td>'.h($v['environment']).'</td><td>'.pill($v['status'],$v['status']==='published'?'green':'orange').($active?' '.pill('Đang dùng','blue'):'').'</td><td><a href="/?p=versions&id='.$v['id'].'">Xem chi tiết →</a></td><td><a href="/?p=export&version='.$v['id'].'">JSON ↓</a></td></tr>';}
  echo '</tbody></table></div></section>';
  $vid=(int)($_GET['id']??0);
  if($vid>0){try{$selected=VersionService::get($db,$vid);ProjectAccess::requireView($db,user(),(int)$selected['project_id']);}catch(Throwable){$selected=null;}}
  else $selected=null;
  if($selected){
    $isActive = ($db['active_versions'][$selected['project_id'].':'.$selected['environment']]??0) === $selected['id'];
    echo '<section class="panel form-panel" style="margin-top:24px; border-top: 4px solid '.($selected['status']==='published'?'#10b981':'#f59e0b').';">';
    echo '<div style="display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:10px;">';
    echo '<div>';
    echo '<h3 style="margin:0 0 6px;">Chi tiết phiên bản v'.(int)$selected['number'].' (#'.(int)$selected['id'].') · Môi trường '.h(strtoupper($selected['environment'])).'</h3>';
    echo '<p class="muted" style="margin:0;">Lý do tạo/phát hành: <b>'.h($selected['reason']).'</b></p>';
    echo '</div>';
    echo '<div style="display:flex; gap:8px;">';
    echo pill($selected['status']==='published'?'ĐÃ PHÁT HÀNH (PUBLISHED)':'BẢN NHÁP (DRAFT)', $selected['status']==='published'?'green':'orange');
    if($isActive) echo pill('ĐANG HOẠT ĐỘNG (ACTIVE)','blue');
    echo '</div>';
    echo '</div>';

    // THÔNG BÁO TÍNH BẤT BIẾN KHI LÀ BẢN ĐÃ PHÁT HÀNH
    if($selected['status']==='published'){
      echo '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #64748b; padding:16px 20px; border-radius:10px; margin:18px 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">';
      echo '<div>';
      echo '<b style="color:#0f172a; font-size:14px;">🔒 Tính bất biến (Immutability): Bản đã phát hành không thể sửa trực tiếp!</b>';
      echo '<div style="font-size:12px; color:#64748b; margin-top:3px;">Theo quy định đề tài: Muốn sửa bản đã phát hành, bạn bắt buộc phải tạo một phiên bản nháp mới kế thừa từ bản này.</div>';
      echo '</div>';
      if(can_edit()){
        form_start('version_draft_create','versions');
        echo '<input type="hidden" name="project_id" value="'.(int)$selected['project_id'].'">';
        echo '<input type="hidden" name="environment" value="'.h($selected['environment']).'">';
        echo '<input type="hidden" name="reason" value="Tạo nháp mới chỉnh sửa từ bản v'.(int)$selected['number'].'">';
        echo '<button class="btn primary small">+ Tạo bản nháp mới để sửa đổi →</button></form>';
      }
      echo '</div>';
    }

    // SO SÁNH PHIÊN BẢN (VERSION DIFF)
    $base=$selected['source_version_id']?VersionService::get($db,(int)$selected['source_version_id']):null;
    if($base){
      $diff=VersionService::diff($base,$selected,$db);
      echo '<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin:20px 0;">';
      echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">';
      echo '<h4 style="margin:0; font-size:15px; color:#1e293b;">⚖️ So sánh cấu hình với bản nguồn: #'.(int)$base['id'].' (v'.(int)$base['number'].')</h4>';
      echo pill(count($diff).' cờ tính năng thay đổi', count($diff)>0?'orange':'neutral');
      echo '</div>';
      if(!empty($diff)){
        echo '<div class="table-wrap"><table><thead><tr><th>Tính năng</th><th>Bản gốc #'.(int)$base['id'].' (v'.(int)$base['number'].')</th><th>Bản hiện tại #'.(int)$selected['id'].' (v'.(int)$selected['number'].')</th></tr></thead><tbody>';
        foreach($diff as $dItem){
          $b = $dItem['before'] ?? [];
          $a = $dItem['after'] ?? [];
          echo '<tr>';
          echo '<td><b>'.h($dItem['feature_name']).'</b></td>';
          echo '<td>'.($b ? pill($b['enabled']?'Bật':'Tắt',$b['enabled']?'green':'neutral').' · Tỷ lệ: '.(int)($b['percentage']??0).'% · Nhóm: '.($b['group_id']?h(name_of($db['groups'],$b['group_id'])):'Tất cả') : '<span class="muted">(Chưa có)</span>').'</td>';
          echo '<td>'.($a ? pill($a['enabled']?'Bật':'Tắt',$a['enabled']?'green':'neutral').' · Tỷ lệ: '.(int)($a['percentage']??0).'% · Nhóm: '.($a['group_id']?h(name_of($db['groups'],$a['group_id'])):'Tất cả') : '<span class="muted">(Đã xóa)</span>').'</td>';
          echo '</tr>';
        }
        echo '</tbody></table></div>';
      } else {
        echo '<p class="muted" style="margin:0; font-size:13px;">Cấu hình giống hoàn toàn với phiên bản gốc (chưa có thay đổi).</p>';
      }
      echo '</div>';
    }

    echo '<h4 style="margin:20px 0 12px; font-size:15px;">Danh sách quy tắc cờ tính năng trong phiên bản này:</h4>';
    foreach($selected['flags'] as $r){
      $feature=Policy::find($db['features'],$r['feature_id']);
      if(!$feature)continue;
      echo '<article class="version-rule" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:18px; margin-bottom:14px;">';
      echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">';
      echo '<h4 style="margin:0;">'.h($feature['name']).' <code>'.h($feature['key']).'</code></h4>';
      echo pill($r['enabled']?'Đang BẬT':'Đang TẮT', $r['enabled']?'green':'neutral');
      echo '</div>';

      if($selected['status']==='draft'&&ProjectAccess::canEdit($db,user(),(int)$selected['project_id'])){
        form_start('version_rule_update','versions');
        echo '<input type="hidden" name="version_id" value="'.$vid.'">';
        echo '<input type="hidden" name="feature_id" value="'.$r['feature_id'].'">';
        echo '<div class="form-grid compact">';
        echo '<label>1. Trạng thái toàn cục<select name="enabled"><option value="1" '.($r['enabled']?'selected':'').'>Bật (Tiếp tục xét thứ tự ưu tiên)</option><option value="0" '.(!$r['enabled']?'selected':'').'>Tắt (Ưu tiên 1 - Tắt toàn bộ)</option></select></label>';
        echo '<label>2. Ưu tiên tắt - Danh sách chặn (Deny List)<input name="deny_users" value="'.h(implode(',',$r['deny_users'])).'" maxlength="4000" placeholder="user0002, bad_user (Ưu tiên 2)"></label>';
        echo '<label>3. Ưu tiên bật - Danh sách cho phép (Allow List)<input name="allow_users" value="'.h(implode(',',$r['allow_users'])).'" maxlength="4000" placeholder="user0001, vip_user (Ưu tiên 3)"></label>';
        echo '<label>4. Giới hạn nhóm thử nghiệm (Cohort Gate)<select name="group_id"><option value="">Tất cả (Không yêu cầu nhóm)</option>';
        foreach($db['groups'] as $g)if($g['project_id']===$selected['project_id'])echo '<option value="'.$g['id'].'" '.($r['group_id']===$g['id']?'selected':'').'>'.h($g['name']).'</option>';
        echo '</select></label>';
        echo '<label>5. Tỷ lệ rollout (0–100%) - SHA-256 cố định<input type="number" name="percentage" min="0" max="100" value="'.$r['percentage'].'" required></label>';
        echo '</div><button class="btn secondary small">💾 Lưu quy tắc vào bản nháp</button></form>';
      }else{
        echo '<div style="font-size:13px; color:#475569; display:flex; gap:16px; flex-wrap:wrap;">';
        echo '<span>Tỷ lệ phân phối: <b>'.(int)$r['percentage'].'%</b></span>';
        echo '<span>Nhóm: <b>'.($r['group_id']===null?'Tất cả':h(name_of($db['groups'],$r['group_id']))).'</b></span>';
        echo '<span>Ưu tiên bật (Allow): <b>'.count($r['allow_users']).' user</b></span>';
        echo '<span>Ưu tiên tắt (Deny): <b>'.count($r['deny_users']).' user</b></span>';
        echo '</div>';
      }
      echo '</article>';
    }

    // THAO TÁC PHÁT HÀNH (PUBLISH) HOẶC KHÔI PHỤC (ROLLBACK) DÀNH CHO ADMIN
    if(is_admin()&&$selected['status']==='draft'){
      echo '<div style="margin-top:24px; padding:20px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">';
      echo '<h4 style="margin:0 0 8px; color:#166534;">🚀 Phát hành phiên bản nháp (Publish to Live)</h4>';
      echo '<p style="margin:0 0 14px; font-size:13px; color:#15803d;">Sau khi phát hành, phiên bản này sẽ trở thành phiên bản hoạt động chính thức và lập tức cung cấp qua API. Bản phát hành sẽ trở thành bất biến.</p>';
      form_start('version_publish','versions');
      echo '<input type="hidden" name="version_id" value="'.$vid.'">';
      echo '<label>Lý do phát hành (ít nhất 5 ký tự)<input name="reason" required minlength="5" maxlength="500" placeholder="Đã kiểm thử hoàn tất, Admin duyệt phát hành"></label>';
      echo '<button class="btn primary">Phát hành phiên bản ngay</button></form>';
      echo '</div>';
    }elseif(is_admin()&&$selected['status']==='published'){
      echo '<div style="margin-top:24px; padding:20px; background:#fff1f2; border:1px solid #fecdd3; border-radius:12px;">';
      echo '<h4 style="margin:0 0 8px; color:#9f1239;">🔄 Khôi phục bản cấu hình này (Rollback)</h4>';
      echo '<p style="margin:0 0 14px; font-size:13px; color:#881337;"><b>Nguyên tắc bài tập lớn:</b> Khôi phục bản cũ sẽ <b>TẠO THÊM MỘT BẢN PHÁT HÀNH MỚI TINH</b> (mang cấu hình của bản này), giữ nguyên toàn bộ các bản cũ và lịch sử mà không bị mất mát.</p>';
      form_start('version_rollback','versions');
      echo '<input type="hidden" name="version_id" value="'.$vid.'">';
      echo '<label>Lý do khôi phục (ít nhất 5 ký tự)<input name="reason" required minlength="5" maxlength="500" placeholder="Phát hiện lỗi bản mới, Admin khôi phục lại cấu hình an toàn"></label>';
      echo '<button class="btn danger">Khôi phục bản này thành phiên bản phát hành MỚI</button></form>';
      echo '</div>';
    }
    echo '</section>';
  }
  echo '<section class="panel table-panel" style="margin-top:24px;">';
  echo '<div class="section-head" style="padding:20px 20px 0;"><div><h3>Lịch sử phát hành & Khôi phục (Không bao giờ xóa)</h3><p>Truy vết toàn bộ các lần phát hành, khôi phục và bản nháp.</p></div></div>';
  echo '<div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Hành động</th><th>Phiên bản</th><th>Lý do</th><th>Ghi chú</th></tr></thead><tbody>';
  $historyList = array_reverse($db['release_history']??[]);
  if(empty($historyList)){
    echo '<tr><td colspan="5" class="empty">Chưa có lịch sử phát hành.</td></tr>';
  } else {
    foreach($historyList as $entry){
      if(!ProjectAccess::canView($db,user(),(int)$entry['project_id']))continue;
      $actionBadge = match($entry['action']??'') {
        'initial' => pill('Khởi tạo','neutral'),
        'publish' => pill('Phát hành','green'),
        'rollback' => pill('Khôi phục (Rollback)','red'),
        default => pill($entry['action']??'','blue')
      };
      $note = !empty($entry['restored_from']) ? 'Khôi phục từ bản #'.(int)$entry['restored_from'] : '-';
      echo '<tr><td>'.h(str_replace('T',' ',substr($entry['at'],0,19))).'</td><td>'.$actionBadge.'</td><td><b>#'.(int)$entry['version_id'].'</b></td><td>'.h($entry['reason']).'</td><td><small>'.$note.'</small></td></tr>';
    }
  }
  echo '</tbody></table></div></section>';
}elseif($page==='api_info'){
  echo '<section class="panel form-panel"><h2>PHP API dành cho ứng dụng thử nghiệm</h2><p>API kiểm tra mã API key trong header <code>X-API-Key</code>, chỉ trả quyết định theo phiên bản đã phát hành.</p><p><code>GET /api/evaluate?project=communitypass&amp;feature=new_registration&amp;env=beta&amp;user=user0001</code></p><p>Mã API key nằm trong <code>storage/api-key.txt</code> và không được commit hoặc chia sẻ công khai.</p><p>Để kiểm thử lỗi API/fallback, chạy ứng dụng độc lập trên cổng 8105 bằng <code>bash lab.sh demo</code>. Tắt API ở cổng 8104, ứng dụng vẫn hiển thị với mặc định OFF.</p></section>';
}elseif($page==='groups'){
  echo '<div class="section-head"><div><h2>Nhóm người dùng thử nghiệm (Cohorts)</h2><p>Quy định đối tượng được phép trải nghiệm tính năng ở môi trường Beta (Ứng với <b>Mức 4: Điều kiện nhóm</b> trong quy tắc ưu tiên).</p></div>'.pill(count($db['groups']).' nhóm','blue').'</div>';
  echo '<div class="cards-grid">';
  foreach($db['groups'] as $g){
    if(!ProjectAccess::canView($db,user(),(int)$g['project_id']))continue;
    $members=array_values(array_filter($db['memberships'],fn($m)=>$m['group_id']===$g['id']));
    $cohortCount = count(array_filter($db['cohort_memberships']??[], fn($cm)=>(int)$cm['group_id']===(int)$g['id']));
    echo '<article class="panel"><div class="group-icon">♧</div><h3>'.h($g['name']).'</h3><p class="muted">Dự án: '.h(name_of($db['projects'],$g['project_id'])).'</p>';
    echo '<div style="display:flex; justify-content:space-between; margin:12px 0 8px; font-size:12px;">';
    echo '<span class="tagline" style="margin:0;">'.count($members).' tài khoản nội bộ</span>';
    if($cohortCount > 0) echo pill($cohortCount.' mã user giả lập', 'blue');
    echo '</div>';
    if($cohortCount > 0) {
      echo '<p style="font-size:11px; color:#64748b; background:#f8fafc; padding:6px 10px; border-radius:6px; margin:0 0 14px;">Chứa các mã kiểm thử BTL: <code>user0001</code>, <code>user0004</code>...</p>';
    }
    echo '<div class="member-list">';
    foreach($members as $m){
      $u=Policy::find($db['users'],$m['user_id']);
      if(!$u) continue;
      echo '<div><span>'.h($u['name']).' · '.h($u['role']).'</span>';
      if(is_admin()){
        form_start('membership_remove','groups');
        echo '<input type="hidden" name="group_id" value="'.$g['id'].'"><input type="hidden" name="user_id" value="'.$u['id'].'"><button class="text-btn">Xóa</button></form>';
      }
      echo '</div>';
    }
    echo '</div>';
    if(is_admin()){
      form_start('membership_add','groups');
      echo '<input type="hidden" name="group_id" value="'.$g['id'].'"><div class="form-grid compact"><label>Thêm tài khoản<select name="user_id">';
      foreach($db['users'] as $u)echo '<option value="'.$u['id'].'">'.h($u['username']).' ('.h($u['role']).')</option>';
      echo '</select></label><button class="btn secondary small align-end">Thêm</button></div></form>';
    }
    echo '</article>';
  }
  echo '</div>';
  if(can_edit()){
    echo '<section class="panel form-panel"><h3>+ Khai báo nhóm thử nghiệm mới</h3>';
    form_start('group_create','groups');
    echo '<div class="form-grid"><label>Dự án<select name="project_id">';
    foreach($db['projects'] as $p)echo '<option value="'.$p['id'].'">'.h($p['name']).'</option>';
    echo '</select></label><label>Tên nhóm<input required name="name" maxlength="80" placeholder="Ví dụ: Early Adopters, Mobile Beta"></label></div><button class="btn primary">+ Tạo nhóm</button></form></section>';
  }
}elseif($page==='approvals'){
  echo '<div class="section-head"><div><h2>Yêu cầu phát hành</h2><p>Admin xét duyệt thay đổi Beta/Production; người gửi không được tự duyệt.</p></div></div><section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Tính năng</th><th>Môi trường</th><th>Đề xuất</th><th>Người gửi</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
  foreach(array_merge(array_values(array_filter(array_reverse($db['requests']),fn($r)=>$r['status']==='pending')),array_values(array_filter(array_reverse($db['requests']),fn($r)=>$r['status']!=='pending'))) as $r){$f=Policy::find($db['features'],$r['feature_id']);if(!$f||!ProjectAccess::canView($db,user(),(int)$f['project_id']))continue;echo '<tr><td><b>'.h($f['name']).'</b><small>'.h(name_of($db['projects'],$f['project_id'])).' · '.h($f['key']).'</small><small>Đề xuất: '.h($r['reason']).'</small>'.(!empty($r['review_note'])?'<small>Phản hồi Admin: '.h($r['review_note']).'</small>':'').'</td><td>'.pill($envLabels[$r['environment']],'blue').'</td><td>'.($r['enabled']?'Bật':'Tắt').' · '.($r['group_id']===null?'Tất cả':h(name_of($db['groups'],$r['group_id']))).'</td><td>'.h(name_of($db['users'],$r['requested_by'])).'</td><td>'.pill($r['status'],$r['status']==='approved'?'green':($r['status']==='rejected'?'red':'orange')).'</td><td>';if(is_admin()&&Policy::canApprove(user(),$r)){form_start('review','approvals');echo '<input type="hidden" name="request_id" value="'.$r['id'].'"><input name="note" maxlength="300" placeholder="Ghi chú" aria-label="Ghi chú duyệt"><div class="review-buttons"><button class="btn primary small" name="decision" value="approved">Duyệt</button><button class="btn danger small" name="decision" value="rejected">Từ chối</button></div></form>';}else echo '<span class="muted">—</span>';echo '</td></tr>';}if(!$db['requests'])echo '<tr><td colspan="6" class="empty">Chưa có yêu cầu nào</td></tr>';echo '</tbody></table></div></section>';
}elseif($page==='preview'){
  $env=(string)($_GET['env']??'beta');if(!in_array($env,Policy::ENVS,true))$env='beta';$projectId=(int)($_GET['project']??1);$project=Policy::find($db['projects'],$projectId);if(!$project||!ProjectAccess::canView($db,user(),(int)$project['id']))$project=null;
  echo '<div class="section-head"><div><h2>CommunityPass · Ứng dụng mẫu</h2><p>Giả lập góc nhìn của người dùng đang đăng nhập để kiểm tra feature flags.</p></div></div><div class="preview-controls"><form method="get"><input type="hidden" name="p" value="preview"><label>Môi trường<select name="env">';foreach(Policy::ENVS as $e)echo '<option value="'.$e.'" '.($env===$e?'selected':'').'>'.h($envLabels[$e]).'</option>';echo '</select></label><label>Dự án<select name="project">';foreach($db['projects'] as $p)if(ProjectAccess::canView($db,user(),(int)$p['id']))echo '<option value="'.$p['id'].'" '.($project&&$project['id']===$p['id']?'selected':'').'>'.h($p['name']).'</option>';echo '</select></label><button class="btn secondary">Xem trạng thái</button></form><span>Đang xem dưới tài khoản <b>'.h(user()['username']).'</b></span></div><section class="sample-app"><div class="sample-heading"><div><div class="eyebrow">EVENTS / COMMUNITY</div><h2>'.h($project['name']??'CommunityPass').'</h2><p>Khám phá tính năng đang được phép hiển thị cho bạn.</p></div>'.pill($envLabels[$env],'blue').'</div><div class="sample-grid">';foreach($db['features'] as $f){if(!$project||$f['project_id']!==$project['id'])continue;$active=Policy::enabledFor($db,$f['id'],$env,user()['id']);echo '<article class="sample-feature"><div class="sample-icon">'.($active?'✧':'🔒').'</div><h3>'.h($f['name']).'</h3><p>'.h($f['description']).'</p>'.pill($active?'ĐÃ MỞ CHO BẠN':'CHƯA CÓ QUYỀN TRUY CẬP',$active?'green':'neutral').'</article>';}echo '</div></section><p class="hint">Đăng nhập bằng tài khoản tester để xác minh chỉ thành viên nhóm Beta được mở tính năng. Trang mẫu không cần cơ sở dữ liệu.</p>';
}elseif($page==='audit'&&is_admin()){
  echo '<div class="section-head"><div><h2>Nhật ký kiểm toán</h2><p>Thay đổi được ghi cùng người thao tác và thời điểm.</p></div></div><section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Chi tiết</th></tr></thead><tbody>';foreach(array_reverse($db['audit']) as $a)echo '<tr><td>'.h(str_replace('T',' ',substr($a['at'],0,19))).'</td><td>'.h(name_of($db['users'],$a['user_id'])).'</td><td>'.pill($a['action'],'blue').'</td><td>'.h($a['detail']).'</td></tr>';if(!$db['audit'])echo '<tr><td colspan="4">Chưa có nhật ký</td></tr>';echo '</tbody></table></div></section>';
}else{echo '<section class="panel"><h2>Không tìm thấy trang</h2><a href="/?p=dashboard">Về tổng quan</a></section>';}
layout_end();
