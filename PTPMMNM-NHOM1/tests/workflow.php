<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$db=json_decode(file_get_contents(__DIR__.'/../storage/data.json'),true,512,JSON_THROW_ON_ERROR);
VersionService::ensure($db);
$tests=0;$failed=0;
function verifyFlow(string $test,bool $condition): void {global $tests,$failed;$tests++;if(!$condition)$failed++;echo ($condition?'PASS':'FAIL').' '.$test."\n";}
function rejectsFlow(callable $fn): bool {try{$fn();return false;}catch(DomainException){return true;}}
$dev=Policy::find($db['users'],2);$tester=Policy::find($db['users'],3);$admin=Policy::find($db['users'],1);
$checks=['functional','permissions','errors'];
$pid=Store::next($db,'projects');$db['projects'][]=['id'=>$pid,'name'=>'Sự kiện thử nghiệm','key'=>'eventtest','description'=>'Dự án mới thử nghiệm quyền','publication_status'=>'draft'];
ProjectAccess::grant($db,$pid,2,'manager');ProjectAccess::grant($db,$pid,3,'viewer');VersionService::ensure($db);
verifyFlow('empty-project-refused',rejectsFlow(fn()=>ReviewWorkflow::submit($db,$dev,'project',$pid,'Kiểm tra dự án chưa có tính năng')));
$db['features'][]=['id'=>Store::next($db,'features'),'project_id'=>$pid,'name'=>'Chức năng ban đầu','key'=>'first_feature','description'=>'Dữ liệu để Tester thử'];
verifyFlow('project-draft-not-published',!ReviewWorkflow::projectIsPublished(Policy::find($db['projects'],$pid)));
verifyFlow('api-refuses-unpublished-project',rejectsFlow(fn()=>ApiService::evaluate($db,'eventtest','new_registration','beta','tester')));
$projectReq=ReviewWorkflow::submit($db,$dev,'project',$pid,'Kiểm tra dự án trước khi phát hành');
verifyFlow('developer-sends-to-tester',$db['release_submissions'][0]['status']==='pending_test');
verifyFlow('admin-cannot-publish-before-test',rejectsFlow(fn()=>ReviewWorkflow::adminResolve($db,$admin,'project',$projectReq,'approved','Duyệt phát hành')));
verifyFlow('developer-cannot-mark-tested',rejectsFlow(fn()=>ReviewWorkflow::testerReview($db,$dev,'project',$projectReq,'tester_approved','Kiểm tra đầy đủ',$checks)));
verifyFlow('tester-must-complete-checklist',rejectsFlow(fn()=>ReviewWorkflow::testerReview($db,$tester,'project',$projectReq,'tester_approved','Đã thử xong',['functional'])));
ReviewWorkflow::testerReview($db,$tester,'project',$projectReq,'tester_rejected','Giao diện thiếu biểu mẫu',[]);
verifyFlow('tester-returns-to-developer',$db['release_submissions'][0]['status']==='tester_rejected');
$projectReq=ReviewWorkflow::submit($db,$dev,'project',$pid,'Sửa lỗi giao diện, kiểm thử lại');
ReviewWorkflow::testerReview($db,$tester,'project',$projectReq,'tester_approved','Đã test thao tác và phân quyền',$checks);
verifyFlow('tester-approval-reaches-admin',$db['release_submissions'][1]['status']==='tester_approved');
ReviewWorkflow::adminResolve($db,$admin,'project',$projectReq,'approved','Duyệt dự án đưa lên Web mẫu');
verifyFlow('admin-publishes-project',ReviewWorkflow::projectIsPublished(Policy::find($db['projects'],$pid)));
verifyFlow('release-record-retained',count($db['release_submissions'])===2);
// Feature request pipeline
$fid=Store::next($db,'features');$db['features'][]=['id'=>$fid,'project_id'=>$pid,'name'=>'Đăng ký an toàn','key'=>'signup_safe','description'=>'Luồng đăng ký có giới hạn'];
foreach(Policy::ENVS as $env){$db['configs'][]=['id'=>Store::next($db,'configs'),'feature_id'=>$fid,'environment'=>$env,'enabled'=>false,'group_id'=>null];}
$requestId=Store::next($db,'requests');
$db['requests'][]=['id'=>$requestId,'feature_id'=>$fid,'environment'=>'beta','enabled'=>true,'group_id'=>null,'reason'=>'Phát hành đăng ký beta','status'=>'pending_test','basis_version_id'=>VersionService::active($db,$pid,'beta')['id'],'requested_by'=>2,'reviewed_by'=>null,'review_note'=>'','created_at'=>date('c')];
verifyFlow('request-must-not-be-admin-approved-yet',!Policy::canApprove($admin,$db['requests'][0]));
ReviewWorkflow::testerReview($db,$tester,'feature',$requestId,'tester_approved','Đã kiểm tra Beta theo checklist',$checks);
verifyFlow('tester-approves-feature',Policy::canApprove($admin,$db['requests'][0]));
verifyFlow('tester-cannot-approve-admin',!Policy::canApprove($tester,$db['requests'][0]));
// Copy admin review's business mutation
$setFeatureConfig=function(array &$data,int $featureId,string $env,bool $enabled){
    foreach($data['configs'] as &$c)if((int)$c['feature_id']===$featureId&&$c['environment']===$env){$c['enabled']=$enabled;return;}
};
$setFeatureConfig($db,$fid,'beta',true);
VersionService::publishCurrent($db,$pid,'beta',1,'Admin duyệt sau Tester', $fid);
verifyFlow('published-feature-appears-in-beta',count(array_filter(VersionService::active($db,$pid,'beta')['flags'],fn($r)=>(int)$r['feature_id']===$fid&&(bool)$r['enabled']))===1);
// Version draft pipeline
$draft=VersionService::createDraft($db,$pid,'beta',2,'Tạo phiên bản cập nhật');
VersionService::updateDraft($db,$draft,$fid,['enabled'=>true,'percentage'=>35,'group_id'=>null,'allow_users'=>[],'deny_users'=>[]]);
$submission=ReviewWorkflow::submit($db,$dev,'version',$draft,'Kiểm tra tỷ lệ rollout 35 phần trăm');
verifyFlow('cannot-publish-version-unverified',rejectsFlow(fn()=>ReviewWorkflow::assertVersionReady($db,$draft)));
verifyFlow('draft-locked-during-testing',ReviewWorkflow::isDraftLocked($db,$draft));
ReviewWorkflow::testerReview($db,$tester,'version',$submission,'tester_approved','Đã kiểm tra tỷ lệ cùng mã người dùng',$checks);
ReviewWorkflow::assertVersionReady($db,$draft);
verifyFlow('version-ready-after-tester',true);
ReviewWorkflow::adminResolve($db,$admin,'version',$submission,'approved','Đã kiểm thử và cho phát hành');
verifyFlow('admin-publishes-version',VersionService::active($db,$pid,'beta')['id']===$draft);
verifyFlow('immutable-release',VersionService::get($db,$draft)['status']==='published');
// Freshness check prevents admin publishing a project modified after tester pass
$pid2=Store::next($db,'projects');$db['projects'][]=['id'=>$pid2,'name'=>'Dự án thay đổi','key'=>'change_test','description'=>'Dữ liệu thử thay đổi','publication_status'=>'draft'];
ProjectAccess::grant($db,$pid2,2,'manager');ProjectAccess::grant($db,$pid2,3,'viewer');
$db['features'][]=['id'=>Store::next($db,'features'),'project_id'=>$pid2,'name'=>'Feature để test thay đổi','key'=>'dummy_flag','description'=>'Dữ liệu QA'];
$stale=ReviewWorkflow::submit($db,$dev,'project',$pid2,'Kiểm tra phiên bản hiện hành');
ReviewWorkflow::testerReview($db,$tester,'project',$stale,'tester_approved','Đã kiểm tra dự án',$checks);
foreach($db['projects'] as &$p)if($p['id']===$pid2){$p['name']='Đã thay đổi sau khi test';break;}unset($p);
verifyFlow('stale-project-refused',rejectsFlow(fn()=>ReviewWorkflow::adminResolve($db,$admin,'project',$stale,'approved','Duyệt dự án thử')));
echo "WORKFLOW_TESTS=$tests FAILED=$failed\n";
exit($failed?1:0);
