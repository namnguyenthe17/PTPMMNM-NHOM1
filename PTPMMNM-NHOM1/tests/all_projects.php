<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$checks=0;$failed=0;
function chk(string $name,bool $pass):void{global $checks,$failed;$checks++;if(!$pass)$failed++;echo ($pass?'PASS ':'FAIL ').$name."\n";}
function reject(callable $f):bool {try{$f();return false;}catch(DomainException){return true;}}
$admin=['id'=>1,'role'=>'admin','name'=>'Admin'];
$dev=['id'=>2,'role'=>'developer','name'=>'Dev'];
$tester=['id'=>3,'role'=>'tester','name'=>'Tester'];
$outsider=['id'=>4,'role'=>'tester','name'=>'Other'];
$db=[
 'projects'=>[['id'=>1,'name'=>'Du an A','key'=>'project_a','publication_status'=>'published'],['id'=>2,'name'=>'Du an B','key'=>'project_b','publication_status'=>'published'],['id'=>3,'name'=>'Du an C','key'=>'project_c','publication_status'=>'draft']],
 'features'=>[['id'=>1,'project_id'=>1,'key'=>'alpha','name'=>'Feature A'],['id'=>2,'project_id'=>2,'key'=>'bravo','name'=>'Feature B'],['id'=>3,'project_id'=>3,'key'=>'charlie','name'=>'Feature C']],
 'configs'=>[['id'=>1,'feature_id'=>1,'environment'=>'beta','enabled'=>true,'group_id'=>null],['id'=>2,'feature_id'=>2,'environment'=>'beta','enabled'=>true,'group_id'=>null],['id'=>3,'feature_id'=>3,'environment'=>'beta','enabled'=>true,'group_id'=>null]],
 'groups'=>[], 'users'=>[$admin,$dev,$tester,$outsider],
 'project_memberships'=>[['project_id'=>1,'user_id'=>3,'permission'=>'viewer'],['project_id'=>2,'user_id'=>3,'permission'=>'viewer'],['project_id'=>3,'user_id'=>3,'permission'=>'viewer'],['project_id'=>1,'user_id'=>2,'permission'=>'editor'],['project_id'=>2,'user_id'=>2,'permission'=>'editor']],
 'cohort_memberships'=>[], 'versions'=>[], 'release_history'=>[], 'decision_logs'=>[], 'active_versions'=>[],
];
VersionService::ensure($db);
chk('project-A-tester-available', ProjectFeatureRuntime::permission($db,$tester,1,1,'beta')[1]['name']==='Feature A');
chk('project-B-tester-available', ProjectFeatureRuntime::permission($db,$tester,2,2,'beta')[1]['name']==='Feature B');
chk('unpublished-C-denied', reject(fn()=>ProjectFeatureRuntime::permission($db,$tester,3,3,'beta')));
chk('wrong-feature-project-denied', reject(fn()=>ProjectFeatureRuntime::permission($db,$tester,1,2,'beta')));
chk('outsider-denied', reject(fn()=>ProjectFeatureRuntime::permission($db,$outsider,1,1,'beta')));
chk('tester-production-denied', reject(fn()=>ProjectFeatureRuntime::permission($db,$tester,1,1,'production')));
$id=ProjectFeatureRuntime::create($db,$tester,1,1,'beta','Bài làm mẫu','Nội dung thuộc dự án A');
$id2=ProjectFeatureRuntime::create($db,$tester,2,2,'beta','Công việc B','Nội dung thuộc dự án B');
chk('create-distinct-records', $id!==$id2 && count($db['project_feature_records'])===2);
chk('records-project-A-scoped', count(ProjectFeatureRuntime::records($db,$tester,1,1))===1 && ProjectFeatureRuntime::records($db,$tester,1,1)[0]['title']==='Bài làm mẫu');
chk('records-project-B-scoped', count(ProjectFeatureRuntime::records($db,$tester,2,2))===1 && ProjectFeatureRuntime::records($db,$tester,2,2)[0]['title']==='Công việc B');
chk('invalid-record-cross-project-denied',reject(fn()=>ProjectFeatureRuntime::updateStatus($db,$tester,$id,2,2,'beta','done')));
ProjectFeatureRuntime::updateStatus($db,$tester,$id,1,1,'beta','done');
chk('status-update-works',ProjectFeatureRuntime::records($db,$tester,1,1)[0]['status']==='done');
chk('other-user-cannot-update',reject(fn()=>ProjectFeatureRuntime::updateStatus($db,$outsider,$id,1,1,'beta','open')));
$db['configs'][0]['enabled']=false; // Published snapshot still true; must not change active without publishing.
chk('published-snapshot-unchanged',ProjectFeatureRuntime::permission($db,$tester,1,1,'beta')[1]['id']===1);
$new=VersionService::createDraft($db,1,'beta',1,'Disable test');
VersionService::updateDraft($db,$new,1,['enabled'=>false,'percentage'=>0,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
VersionService::publish($db,$new,1,'Admin approved disable feature');
chk('disabled-release-denied',reject(fn()=>ProjectFeatureRuntime::permission($db,$tester,1,1,'beta')));
chk('input-validation',reject(fn()=>ProjectFeatureRuntime::create($db,$tester,2,2,'beta','a',str_repeat('b',3000))));
chk('dedicated-module-known',ProjectFeatureRuntime::dedicatedSlug(['key'=>'dark_theme'])==='dark');
chk('generic-module-not-faked',ProjectFeatureRuntime::dedicatedSlug(['key'=>'new_character'])===null);
echo "ALL_PROJECTS_TESTS=$checks FAILED=$failed\n";
exit($failed?1:0);
