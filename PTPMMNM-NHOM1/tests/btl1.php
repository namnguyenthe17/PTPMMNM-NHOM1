<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$tests=0;$failed=0;
function check(string $name,mixed $actual,mixed $expected): void {
    global $tests,$failed;$tests++;
    if($actual!==$expected){$failed++;echo "FAIL $name expected=".var_export($expected,true)." got=".var_export($actual,true)."\n";}
    else echo "PASS $name\n";
}
$db=['projects'=>[['id'=>1,'key'=>'demo','name'=>'Demo']],
'features'=>[['id'=>1,'project_id'=>1,'key'=>'f_one','name'=>'Feature One']],
'groups'=>[['id'=>1,'project_id'=>1,'name'=>'Beta']],
'configs'=>[['id'=>1,'feature_id'=>1,'environment'=>'beta','enabled'=>true,'group_id'=>null]],
'users'=>[['id'=>1,'role'=>'admin'],['id'=>2,'role'=>'developer'],['id'=>3,'role'=>'tester']],
'project_memberships'=>[['project_id'=>1,'user_id'=>2,'permission'=>'viewer']],
'cohort_memberships'=>[['group_id'=>1,'user_code'=>'user0001']],'versions'=>[],
'active_versions'=>[],'release_history'=>[],'decision_logs'=>[]];
VersionService::ensure($db);
$v=VersionService::active($db,1,'beta');
check('migrate-creates-version',$v['status'],'published');
$draft=VersionService::createDraft($db,1,'beta',1,'Thử nghiệm tỷ lệ');
VersionService::updateDraft($db,$draft,1,['enabled'=>true,'percentage'=>30,'group_id'=>null,'allow_users'=>['user0001'],'deny_users'=>['user0002']]);
$draftV=VersionService::get($db,$draft);
$one=FlagEngine::evaluate($db,$draftV,1,'user0234');
check('stable-percentage',FlagEngine::evaluate($db,$draftV,1,'user0234'),$one);
check('deterministic-bucket',FlagEngine::bucket(1,'beta',$draft,1,'user0234'),FlagEngine::bucket(1,'beta',$draft,1,'user0234'));
check('priority-allow',FlagEngine::evaluate($db,$draftV,1,'user0001')['enabled'],true);
check('priority-deny',FlagEngine::evaluate($db,$draftV,1,'user0002')['enabled'],false);
VersionService::updateDraft($db,$draft,1,['enabled'=>true,'percentage'=>0,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
check('rollout-0',FlagEngine::evaluate($db,VersionService::get($db,$draft),1,'user0999')['enabled'],false);
VersionService::updateDraft($db,$draft,1,['enabled'=>true,'percentage'=>100,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
check('rollout-100',FlagEngine::evaluate($db,VersionService::get($db,$draft),1,'user0999')['enabled'],true);
VersionService::updateDraft($db,$draft,1,['enabled'=>true,'percentage'=>100,'group_id'=>1,'allow_users'=>[],'deny_users'=>[]]);
check('cohort-member',FlagEngine::evaluate($db,VersionService::get($db,$draft),1,'user0001')['enabled'],true);
check('cohort-outsider',FlagEngine::evaluate($db,VersionService::get($db,$draft),1,'user0002')['enabled'],false);
check('viewer-cannot-edit',ProjectAccess::canEdit($db,['id'=>2,'role'=>'developer'],1),false);
ProjectAccess::grant($db,1,2,'editor');
check('editor-can-edit',ProjectAccess::canEdit($db,['id'=>2,'role'=>'developer'],1),true);
VersionService::publish($db,$draft,1,'Đã kiểm thử và phê duyệt');
try{VersionService::updateDraft($db,$draft,1,['enabled'=>false,'percentage'=>50,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);$blocked=false;}catch(DomainException){$blocked=true;}
check('published-immutable',$blocked,true);
check('version-now-active',VersionService::active($db,1,'beta')['id'],$draft);
$before=VersionService::get($db,$draft);
$second=VersionService::createDraft($db,1,'beta',1,'Tắt tính năng');
VersionService::updateDraft($db,$second,1,['enabled'=>false,'percentage'=>0,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
check('diff-found',count(VersionService::diff($before,VersionService::get($db,$second),$db)),1);
VersionService::publish($db,$second,1,'Ngừng dùng tính năng beta');
check('after-publish-off',ApiService::evaluate($db,'demo','f_one','beta','user0001')['enabled'],false);
$restored=VersionService::rollback($db,$draft,1,'Lỗi cần quay về bản cũ');
check('rollback-creates-new-version',$restored!==$draft,true);
check('rollback-restores-on',ApiService::evaluate($db,'demo','f_one','beta','user0001')['enabled'],true);
check('rollback-retains-history',count($db['release_history'])>=5,true);
check('old-release-unchanged',VersionService::get($db,$draft),$before);
$logged=[];
$client=DemoClient::decide(fn()=>throw new RuntimeException('API ngắt'),function(string $s)use(&$logged){$logged[]=$s;});
check('api-fallback-off',$client['enabled'],false);
check('api-fallback-marked',$client['fallback'],true);
check('api-failure-logged',count($logged),1);
$encoded=VersionService::exportJson(VersionService::get($db,$draft));
check('export-json-format',json_decode($encoded,true)['format'],'flagflow-v1');
$newImport=VersionService::importDraft($db,1,'beta',1,$encoded);
check('import-keeps-draft',VersionService::get($db,$newImport)['status'],'draft');
try{VersionService::importDraft($db,1,'beta',1,'{"format":"flagflow-v1","project_id":999,"environment":"beta","flags":[]}');$badAccepted=true;}catch(DomainException){$badAccepted=false;}
check('reject-wrong-project-import',$badAccepted,false);
$old=VersionService::active($db,1,'beta');
try{VersionService::publish($db,$newImport,1,'Quy tắc nhập lại sau thay đổi');$accepted=true;}catch(DomainException){$accepted=false;}
check('import-from-active-can-publish',$accepted,true);
$latest=VersionService::active($db,1,'beta');
check('stable-with-selected-version',ApiService::evaluate($db,'demo','f_one','beta','user0001',$draft)['enabled'],true);
check('latest-api-version',$latest['id'],$newImport);
check('historical-api-version',ApiService::evaluate($db,'demo','f_one','beta','user0001',$draft)['version'],$draft);
check('api-version-env-isolation',isset($db['active_versions']['1:production']),true);
check('sample-user-format',FlagEngine::evaluate($db,$latest,1,'../danger')['enabled'],false);
// A viewer must never mutate, even on a forged POST request; access check is server-side.
try{ProjectAccess::requireEdit($db,['id'=>3,'role'=>'tester'],1);$forged=true;}catch(DomainException){$forged=false;}
check('forged-viewer-post-refused',$forged,false);
echo "BTL1_TESTS=$tests FAILED=$failed\n";exit($failed?1:0);
