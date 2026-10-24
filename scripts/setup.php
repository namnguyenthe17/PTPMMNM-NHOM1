<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if (PHP_SAPI!=='cli') exit(1);
$target=__DIR__.'/../storage/data.json';
if (file_exists($target)) { fwrite(STDERR,"Dữ liệu đã tồn tại; không ghi đè.\n"); exit(1); }
function makepass(): string { return bin2hex(random_bytes(8)); }
$accounts=[['admin','Quản trị viên','admin'],['dev','Lập trình viên','developer'],['tester','Người thử nghiệm','tester']];
$users=[];$passwords=[];
foreach($accounts as $i=>$a) { $pass=makepass(); $passwords[$a[0]]=$pass; $users[]=['id'=>$i+1,'username'=>$a[0],'name'=>$a[1],'role'=>$a[2],'password_hash'=>password_hash($pass,PASSWORD_DEFAULT)]; }
$db=['users'=>$users,'projects'=>[['id'=>1,'name'=>'CommunityPass','key'=>'communitypass','description'=>'Ứng dụng đăng ký tham dự sự kiện dựa trên Lab 1 và Lab 2']], 'features'=>[
 ['id'=>1,'project_id'=>1,'key'=>'new_registration','name'=>'Đăng ký sự kiện phiên bản mới','description'=>'Thử nghiệm luồng đăng ký mới'],
 ['id'=>2,'project_id'=>1,'key'=>'smart_waitlist','name'=>'Danh sách chờ thông minh','description'=>'Thử nghiệm danh sách chờ khi đủ chỗ'],
 ['id'=>3,'project_id'=>1,'key'=>'dark_theme','name'=>'Giao diện tối','description'=>'Bật giao diện màu tối cho người tham gia']],
 'groups'=>[['id'=>1,'project_id'=>1,'name'=>'Beta Testers','description'=>'Nhóm dùng thử tính năng mới']],
 'memberships'=>[['group_id'=>1,'user_id'=>3]],
 'configs'=>[], 'requests'=>[], 'audit'=>[]];
foreach($db['features'] as $f) foreach(Policy::ENVS as $env) $db['configs'][]=['id'=>count($db['configs'])+1,'feature_id'=>$f['id'],'environment'=>$env,'enabled'=>false,'group_id'=>null,'updated_at'=>date('c')];
if (!is_dir(dirname($target))) mkdir(dirname($target),0700,true);
file_put_contents($target,json_encode($db,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);chmod($target,0600);
echo "Khởi tạo thành công. Mật khẩu chỉ hiển thị một lần; KHÔNG chia sẻ hoặc commit:\n";
foreach($passwords as $name=>$pass) echo "$name: $pass\n";
echo "Đăng nhập qua http://127.0.0.1:8101\n";
