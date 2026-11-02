<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit(2);
if(!in_array('--apply',$argv,true)){
    fwrite(STDOUT,"Chế độ xem trước: script này sẽ PHÁT HÀNH BẢN BETA MỚI và bật 7 cờ CommunityPass.\nChỉ sử dụng cho dữ liệu demo, không dùng trên dự án thật.\nChạy: php scripts/enable-community-demo.php --apply\n");
    exit(0);
}
$out=store()->transaction(function(array &$db):string{
    VersionService::ensure($db);
    $project=null;foreach($db['projects'] as $p)if($p['key']==='communitypass'){$project=$p;break;}
    if(!$project)throw new DomainException('Không có dự án CommunityPass');
    $pid=(int)$project['id'];
    $all=['new_registration','smart_waitlist','dark_theme','push_notification','advanced_search','smart_filter','new_dashboard'];
    $ids=[];foreach($db['features'] as $f)if((int)$f['project_id']===$pid&&in_array($f['key'],$all,true))$ids[]=(int)$f['id'];
    if(count($ids)!==count($all))throw new DomainException('Chưa đủ 7 tính năng cần thiết');
    $admin=null;foreach($db['users'] as $u)if($u['role']==='admin'){$admin=$u;break;}
    if(!$admin)throw new DomainException('Chưa có Admin');
    $v=VersionService::active($db,$pid,'beta');
    $ready=true;
    foreach($ids as $id){$rule=null;foreach($v['flags'] as $f)if((int)$f['feature_id']===$id){$rule=$f;break;}
        if(!$rule||!$rule['enabled']||(int)($rule['percentage']??0)!==100||$rule['group_id']!==null){$ready=false;break;}
    }
    if($ready)return 'Các cờ Beta demo đã được phát hành từ trước; không tạo thêm phiên bản.';
    $draft=VersionService::createDraft($db,$pid,'beta',(int)$admin['id'],'Tạo dữ liệu trình diễn CommunityPass');
    foreach($ids as $id)VersionService::updateDraft($db,$draft,$id,['enabled'=>true,'percentage'=>100,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
    VersionService::publish($db,$draft,(int)$admin['id'],'Phát hành phiên bản Beta minh họa CommunityPass');
    return 'Đã phát hành bản Beta minh họa #'.$draft.' và bật 7 cờ tính năng cho dữ liệu demo.';
});
echo $out."\n";
