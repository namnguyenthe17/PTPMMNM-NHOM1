<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit(2);
// Idempotent fixture only. Existing records are never overwritten.
$count=store()->transaction(function(array &$db): int {
    CommunityService::ensure($db);
    if(count($db['community_events']))return 0;
    $dev=null;foreach($db['users'] as $u)if($u['role']==='developer'){$dev=$u;break;}
    if(!$dev)throw new RuntimeException('Cần tài khoản Developer để tạo dữ liệu mẫu');
    $projects=array_values(array_filter($db['projects'],fn($p)=>ProjectAccess::canEdit($db,$dev,(int)$p['id'])));
    if(!$projects)throw new RuntimeException('Developer chưa có quyền chỉnh sửa dự án');
    $pid=(int)$projects[0]['id'];
    $specs=[
        ['Ngày hội trải nghiệm CommunityPass','community','Thử nghiệm đăng ký online và danh sách chờ tại sự kiện cộng đồng.','Hà Nội','+14 days',2],
        ['Workshop PHP và MySQL thực hành','workshop','Học cách phát triển tính năng với PHP, MySQL và phân quyền.','Phòng Lab A','+21 days',30],
        ['Giao lưu cộng đồng công nghệ','technology','Trao đổi kinh nghiệm triển khai website và phát hành tính năng an toàn.','Hội trường B','+28 days',50]
    ];
    $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
    foreach($specs as [$name,$cat,$description,$location,$offset,$capacity]){
        $day=(new DateTimeImmutable('now',$tz))->modify($offset)->setTime(9,0);
        CommunityService::createEvent($db,$dev,$pid,$name,$cat,$description,$location,$day->format('Y-m-d\TH:i'),$capacity);
    }
    return count($specs);
});
echo $count>0?"Đã thêm $count sự kiện minh họa.\n":"Đã có sự kiện; không thêm trùng hoặc ghi đè.\n";
