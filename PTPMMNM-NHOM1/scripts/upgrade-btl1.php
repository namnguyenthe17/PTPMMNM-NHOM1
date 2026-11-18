<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit(2);
if (!is_file(__DIR__.'/../storage/data.json')) {fwrite(STDERR,"Chưa có dữ liệu, chạy php scripts/setup.php trước.\n");exit(2);}
$added=store()->transaction(function(array &$db): array {
    VersionService::ensure($db);
    $project=$db['projects'][0]??null;
    if(!$project)throw new RuntimeException('Không có dự án mẫu');
    $pid=(int)$project['id'];
    $groupNames=['Early Adopters','Mobile Beta'];
    foreach($groupNames as $name){
        $exists=false;foreach($db['groups'] as $g)if($g['project_id']===$pid&&$g['name']===$name)$exists=true;
        if(!$exists)$db['groups'][]=['id'=>Store::next($db,'groups'),'project_id'=>$pid,'name'=>$name,'description'=>'Nhóm dữ liệu thử giả lập'];
    }
    $flagNames=[
        ['fast_checkout','Thanh toán nhanh'],['ai_recommendation','Gợi ý nội dung'],
        ['new_dashboard','Bảng điều khiển mới'],['push_notification','Thông báo đẩy'],
        ['advanced_search','Tìm kiếm nâng cao'],['compact_layout','Giao diện gọn'],
        ['smart_filter','Bộ lọc thông minh'],['activity_feed','Bảng tin hoạt động'],
        ['accessibility_mode','Chế độ trợ năng'],['multi_language','Đa ngôn ngữ']
    ];
    foreach($flagNames as [$key,$name]){
        $exists=false;foreach($db['features'] as $f)if($f['project_id']===$pid&&$f['key']===$key)$exists=true;
        if(!$exists){$id=Store::next($db,'features');$db['features'][]=['id'=>$id,'project_id'=>$pid,'key'=>$key,'name'=>$name,'description'=>'Tính năng minh họa phục vụ bài tập lớn'];
            foreach(Policy::ENVS as $env)$db['configs'][]=['id'=>Store::next($db,'configs'),'feature_id'=>$id,'environment'=>$env,'enabled'=>false,'group_id'=>null,'updated_at'=>date('c')];
        }
    }
    $groups=array_values(array_filter($db['groups'],fn($g)=>$g['project_id']===$pid));
    $db['sample_users'] ??= [];
    if(count($db['sample_users'])<1000){$db['sample_users']=[];for($i=1;$i<=1000;$i++)$db['sample_users'][]=sprintf('user%04d',$i);}
    // Recreate sample cohort mapping idempotently; preserve custom memberships for other user codes.
    $db['cohort_memberships']=array_values(array_filter($db['cohort_memberships'],fn($m)=>!preg_match('/^user\d{4}$/D',$m['user_code'])));
    for($i=1;$i<=1000;$i++){
        $g=$groups[($i-1)%count($groups)];
        $db['cohort_memberships'][]=['group_id'=>(int)$g['id'],'user_code'=>sprintf('user%04d',$i)];
    }
    foreach($db['projects'] as $p) {
        foreach($db['users'] as $u){
            if($u['role']==='developer')ProjectAccess::grant($db,(int)$p['id'],(int)$u['id'],'editor');
            if($u['role']==='tester'&&$p['id']===$pid)ProjectAccess::grant($db,(int)$p['id'],(int)$u['id'],'viewer');
        }
    }
    // Add new flags to active snapshots without rewriting already published snapshots:
    // create a fresh immutable version for each environment when new features were added.
    foreach($db['projects'] as $p)foreach(Policy::ENVS as $env){
        $active=VersionService::active($db,(int)$p['id'],$env);
        $currentIds=array_column($active['flags'],'feature_id');
        $hasNew=false;
        foreach($db['features'] as $f)if($f['project_id']===$p['id']&&!in_array($f['id'],$currentIds,true)){$hasNew=true;break;}
        if($hasNew)VersionService::publishCurrent($db,(int)$p['id'],$env,1,'Cập nhật bộ tính năng mẫu');
    }
    // API key generated only once, stored hashed in db; plaintext in private local file.
    if(empty($db['api_key_hash'])){
        $plain=bin2hex(random_bytes(32));
        $db['api_key_hash']=hash('sha256',$plain);
        $path=__DIR__.'/../storage/api-key.txt';
        file_put_contents($path,$plain,LOCK_EX);chmod($path,0600);
    }
    return ['projects'=>count($db['projects']),'features'=>count($db['features']),'groups'=>count($db['groups']),'sample_users'=>count($db['sample_users']),'versions'=>count($db['versions'])];
});
echo "NANG CAP BTL1 THANH CONG\n";
foreach($added as $k=>$v)echo "$k=$v\n";
echo "Da giu nguyen tai khoan, mat khau va yeu cau duyet cu. Khong commit storage/.\n";
