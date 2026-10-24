<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$count=0;$failed=0;
function verify(string $label,bool $ok):void {global $count,$failed;$count++;if(!$ok)$failed++;echo ($ok?'PASS ':'FAIL ').$label."\n";}
function fixture():array {return ['projects'=>[['id'=>1,'name'=>'Demo']], 'features'=>[], 'groups'=>[['id'=>7,'project_id'=>1]], 'configs'=>[], 'requests'=>[], 'memberships'=>[], 'audit'=>[]];}
$dev=['id'=>2,'role'=>'developer'];$admin=['id'=>1,'role'=>'admin'];$other=['id'=>3,'role'=>'tester'];
$db=fixture();$id=FeatureSubmission::submit($db,$dev,1,'hero_new','Tướng mới','Demo','beta',7,'Mời nhóm kiểm thử');
verify('developer-submit-beta',$id===1&&count($db['requests'])===1&&$db['requests'][0]['status']==='pending');
verify('development-active',Policy::enabledFor($db,1,'development',2));
verify('beta-disabled-before-approval',!Policy::enabledFor($db,1,'beta',2));
verify('admin-can-approve',Policy::canApprove($admin,$db['requests'][0]));
verify('developer-cannot-approve',!Policy::canApprove($dev,$db['requests'][0]));
$db['requests'][0]['status']='approved';$db['configs'][1]['enabled']=true;$db['configs'][1]['group_id']=7;$db['memberships'][]=['user_id'=>3,'group_id'=>7];
verify('beta-member-after-approval',Policy::enabledFor($db,1,'beta',3));
verify('beta-nonmember-after-approval',!Policy::enabledFor($db,1,'beta',2));
try{FeatureSubmission::submit($db,$dev,1,'hero_new','Tướng cũ','','beta',7,'Mời dùng thử');verify('duplicate-prevented',false);}catch(DomainException $e){verify('duplicate-prevented',true);}
try{FeatureSubmission::submit($db,$other,1,'new_xxx','Tướng cũ','','beta',7,'Mời dùng thử');verify('tester-prevented',false);}catch(DomainException $e){verify('tester-prevented',true);}
try{FeatureSubmission::submit($db,$dev,1,'new_xxx','Tướng cũ','','beta',999,'Mời dùng thử');verify('invalid-group-prevented',false);}catch(DomainException $e){verify('invalid-group-prevented',true);}
echo "SUBMISSION_TESTS=$count FAILED=$failed\n";exit($failed?1:0);
