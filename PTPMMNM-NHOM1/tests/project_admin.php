<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$tests=0;$failed=0;
function verify(string $name,bool $ok): void {global $tests,$failed;$tests++;$failed+=!$ok;echo ($ok?'PASS ':'FAIL ').$name."\n";}
$db=[
  'projects'=>[['id'=>1,'name'=>'CommunityPass','key'=>'communitypass','description'=>'Demo'],['id'=>2,'name'=>'Dự án khác','key'=>'otherapp','description'=>'Khác']],
  'features'=>[['id'=>1,'project_id'=>1],['id'=>2,'project_id'=>2]],
  'groups'=>[['id'=>1,'project_id'=>1],['id'=>2,'project_id'=>2]],
  'configs'=>[['id'=>1,'feature_id'=>1],['id'=>2,'feature_id'=>2]],
  'versions'=>[['id'=>1,'project_id'=>1],['id'=>2,'project_id'=>2]],
  'release_history'=>[['project_id'=>1],['project_id'=>2]],
  'requests'=>[['feature_id'=>1],['feature_id'=>2]],
  'project_memberships'=>[['project_id'=>1,'user_id'=>3],['project_id'=>2,'user_id'=>3]],
  'release_submissions'=>[['id'=>1,'type'=>'project','project_id'=>1,'target_id'=>1,'status'=>'approved'],['id'=>2,'type'=>'project','project_id'=>2,'target_id'=>2,'status'=>'approved']],
  'active_versions'=>['1:beta'=>1,'2:beta'=>2],
  'community_events'=>[['id'=>1,'project_id'=>1],['id'=>2,'project_id'=>2]],
  'community_registrations'=>[['event_id'=>1],['event_id'=>2]],
  'project_feature_records'=>[['project_id'=>1,'feature_id'=>1],['project_id'=>2,'feature_id'=>2]],
  'users'=>[['id'=>1,'name'=>'Admin'],['id'=>3,'name'=>'Tester']],
  'audit'=>[['id'=>1,'detail'=>'keep']]
];
ProjectAdministration::update($db,1,'Dự án thử nghiệm','Không chứa tên Lab');
verify('rename-project',($db['projects'][0]['name']??'')==='Dự án thử nghiệm');
verify('preserve-project-key',($db['projects'][0]['key']??'')==='communitypass');
verify('admin-project-view-versus-flag',ProjectAccess::canView($db,['id'=>1,'role'=>'admin'],1));
try {ProjectAdministration::update($db,1,'a','bad');$bad=false;}catch(DomainException){$bad=true;}
verify('reject-short-name',$bad);
ProjectAdministration::delete($db,1);
verify('removed-target-project',count($db['projects'])===1 && $db['projects'][0]['id']===2);
foreach(['features','groups','configs','versions','release_history','requests','project_memberships','release_submissions','community_events','community_registrations','project_feature_records'] as $key){
  verify('preserve-unrelated-'.$key,count($db[$key]??[])===1);
}
verify('preserve-unrelated-active-version',isset($db['active_versions']['2:beta']) && !isset($db['active_versions']['1:beta']));
verify('preserve-accounts-and-audit',count($db['users'])===2 && count($db['audit'])===1);
$db['release_submissions'][]=['id'=>3,'type'=>'project','project_id'=>2,'target_id'=>2,'status'=>'pending_test'];
try {ProjectAdministration::update($db,2,'Tên đã đổi','Mô tả');$block=false;}catch(DomainException){$block=true;}
verify('no-rename-during-tester-review',$block);
echo "PROJECT_ADMIN_TESTS=$tests FAILED=$failed\n";
exit($failed?1:0);
