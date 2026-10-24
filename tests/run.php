<?php
declare(strict_types=1);
require __DIR__.'/../app/Store.php';require __DIR__.'/../app/Policy.php';
$tests=0;$fails=0;
function same(string $case,mixed $actual,mixed $expected): void { global $tests,$fails; $tests++; if($actual!==$expected){$fails++; echo "FAIL $case expected=".var_export($expected,true)." got=".var_export($actual,true)."\n";} else echo "PASS $case\n"; }
$db=['features'=>[['id'=>1,'project_id'=>1]],'configs'=>[['feature_id'=>1,'environment'=>'beta','enabled'=>true,'group_id'=>2]],'memberships'=>[['user_id'=>4,'group_id'=>2]]];
same('beta-member',Policy::enabledFor($db,1,'beta',4),true);
same('beta-nonmember',Policy::enabledFor($db,1,'beta',5),false);
same('production-off',Policy::enabledFor($db,1,'production',4),false);
$db['configs'][0]['group_id']=null;
same('all-users',Policy::enabledFor($db,1,'beta',5),true);
$db['configs'][0]['enabled']=false;
same('disabled',Policy::enabledFor($db,1,'beta',4),false);
same('approval-separation',Policy::canApprove(['id'=>9,'role'=>'admin'],['requested_by'=>9,'status'=>'pending']),false);
same('admin-approves',Policy::canApprove(['id'=>9,'role'=>'admin'],['requested_by'=>2,'status'=>'pending']),true);
same('developer-cannot-approve',Policy::canApprove(['id'=>3,'role'=>'developer'],['requested_by'=>2,'status'=>'pending']),false);
same('development-direct',Policy::canDirectToggle('developer','development'),true);
same('beta-requires-approval',Policy::canDirectToggle('developer','beta'),false);
echo "TESTS=$tests FAILED=$fails\n";exit($fails?1:0);
