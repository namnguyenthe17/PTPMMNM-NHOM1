<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$count=0;$failed=0;
function verifyCommunity(string $name,bool $condition):void {global $count,$failed;$count++;if(!$condition)$failed++;echo ($condition?'PASS':'FAIL')." $name\n";}
$db=json_decode(file_get_contents(__DIR__.'/../storage/data.json'),true,512,JSON_THROW_ON_ERROR);
CommunityService::ensure($db);
// Work with a pristine in-memory app copy. Registration test never modifies the fixture.
$db['community_registrations']=[];$db['community_notifications']=[];$db['community_activity']=[];
$pid=1;$dev=['id'=>2,'role'=>'developer'];
$testers=[];
foreach([3,4,5] as $id){$testers[]=['id'=>$id,'role'=>'tester'];if(!Policy::find($db['users'],$id))$db['users'][]=['id'=>$id,'role'=>'tester'];ProjectAccess::grant($db,$pid,$id,'viewer');}
$start=(new DateTimeImmutable('+5 days',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d\TH:i');
$eid=CommunityService::createEvent($db,$dev,$pid,'Kiểm thử đăng ký thật','workshop','Thử nghiệm','Phòng Lab B',$start,2);
verifyCommunity('developer-create-event',CommunityService::event($db,$eid)['capacity']===2);
try {CommunityService::createEvent($db,$testers[0],$pid,'Sự kiện sai','workshop','Mô tả','Phòng Lab B',$start,2);$blocked=false;}catch(DomainException){$blocked=true;}
verifyCommunity('tester-cannot-create-event',$blocked);
verifyCommunity('feature-flag-approved',CommunityService::enabled($db,$pid,'beta','new_registration',$testers[0]));
verifyCommunity('first-registration',CommunityService::register($db,$testers[0],$eid,'beta')==='confirmed');
verifyCommunity('second-registration',CommunityService::register($db,$testers[1],$eid,'beta')==='confirmed');
verifyCommunity('waitlist-registration',CommunityService::register($db,$testers[2],$eid,'beta')==='waitlisted');
verifyCommunity('no-overbooking',CommunityService::status($db,$eid)['confirmed']===2);
try{CommunityService::register($db,$testers[0],$eid,'beta');$blocked=false;}catch(DomainException){$blocked=true;}
verifyCommunity('reject-duplicate',$blocked);
try{CommunityService::register($db,$testers[0],$eid,'development');$blocked=false;}catch(DomainException){$blocked=true;}
verifyCommunity('tester-cannot-use-development',$blocked);
try{CommunityService::cancel($db,['id'=>99,'role'=>'tester'],$eid);$blocked=false;}catch(DomainException){$blocked=true;}
verifyCommunity('cannot-cancel-other-user',$blocked);
CommunityService::cancel($db,$testers[0],$eid);
verifyCommunity('waitlist-auto-promote',CommunityService::registration($db,$eid,5)['status']==='confirmed');
verifyCommunity('capacity-still-respected',CommunityService::status($db,$eid)['confirmed']===2);
verifyCommunity('notifications-created',count($db['community_notifications'])>=5);
$nid=$db['community_notifications'][0]['id'];
try{CommunityService::markRead($db,['id'=>99,'role'=>'tester'],$nid);$blocked=false;}catch(DomainException){$blocked=true;}
verifyCommunity('cannot-read-other-users-notification',$blocked);
CommunityService::markRead($db,$testers[0],$nid);
verifyCommunity('mark-own-notification-read',!empty($db['community_notifications'][0]['read_at']));
// Flip a published feature off in memory and ensure direct POST logic will fail closed.
$draft=VersionService::createDraft($db,1,'beta',1,'Disable registration');
VersionService::updateDraft($db,$draft,1,['enabled'=>false,'percentage'=>0,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
VersionService::publish($db,$draft,1,'Disable registration for testing');
try{CommunityService::register($db,$testers[0],$eid,'beta');$blocked=false;}catch(DomainException){$blocked=true;}
verifyCommunity('deny-feature-after-publish-off',$blocked);
echo "COMMUNITY_TESTS=$count FAILED=$failed\n";
exit($failed?1:0);
