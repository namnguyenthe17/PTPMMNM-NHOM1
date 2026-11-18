<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$pass=0; $fail=0;
function proofCheck(string $label, bool $value): void {
    global $pass,$fail;
    echo ($value?'PASS ':'FAIL ').$label."\n";
    $value?$pass++:$fail++;
}
$state=['users'=>[], 'remembered_accounts'=>[]];
$aliceId=AccountService::create($state,'admin_abc','Quản trị','test-Pass123456','admin');
$bobId=AccountService::create($state,'dev_xye','Lập trình viên','test-Pass234567','developer');
$testId=AccountService::create($state,'tester_zz','Tester nội bộ','test-Pass345678','tester');
$one=str_repeat('a',64); $two=str_repeat('b',64);
proofCheck('empty_initial_list', RememberedAccounts::list($state,$one)===[]);
RememberedAccounts::remember($state,$state['users'][0],$one);
RememberedAccounts::remember($state,$state['users'][1],$one);
RememberedAccounts::remember($state,$state['users'][2],$two);
proofCheck('two_saved_accounts_on_device_one',count(RememberedAccounts::list($state,$one))===2);
proofCheck('one_saved_account_on_device_two',count(RememberedAccounts::list($state,$two))===1);
proofCheck('saved_admin_can_switch', RememberedAccounts::verify($state,$one,$aliceId)['role']==='admin');
proofCheck('saved_dev_can_switch', RememberedAccounts::verify($state,$one,$bobId)['role']==='developer');
proofCheck('cross_device_denied', RememberedAccounts::verify($state,$two,$aliceId)===null);
proofCheck('unknown_account_denied', RememberedAccounts::verify($state,$one,98765)===null);
proofCheck('garbled_cookie_denied', RememberedAccounts::list($state,'bad-token')===[]);
proofCheck('no_plaintext_device_secret', strpos(json_encode($state),$one)===false && strpos(json_encode($state),$two)===false);
proofCheck('no_plaintext_password', !str_contains(json_encode($state),'test-Pass123456'));
// Simulate an Admin password reset, invalidating all remembered devices.
AccountService::changePassword($state,$aliceId,'reset-Test99999');
proofCheck('password_change_invalidates_device_login',RememberedAccounts::verify($state,$one,$aliceId)===null);
proofCheck('other_user_not_invalidated',RememberedAccounts::verify($state,$one,$bobId)!==null);
// Remove per device only.
RememberedAccounts::forget($state,$one,$bobId);
proofCheck('forget_selected_device',RememberedAccounts::list($state,$one)===[]);
proofCheck('forget_does_not_affect_other_device',RememberedAccounts::verify($state,$two,$testId)!==null);
RememberedAccounts::revokeAll($state,$testId);
proofCheck('revoke_all_devices',RememberedAccounts::list($state,$two)===[]);
// Expiration.
RememberedAccounts::remember($state,$state['users'][1],$one);
$state['remembered_accounts'][count($state['remembered_accounts'])-1]['expires_at']=time()-1;
proofCheck('expired_session_not_switchable',RememberedAccounts::verify($state,$one,$bobId)===null);
// Per-device cap.
$big=['users'=>[],'remembered_accounts'=>[]];
for($i=0;$i<10;$i++) {
    $uid=AccountService::create($big,'member_'.str_pad((string)$i,2,'0',STR_PAD_LEFT),'Team Member '.($i+1),'pass-ABC123456','tester');
    RememberedAccounts::remember($big,$big['users'][$i],$one);
}
proofCheck('at_most_eight_per_device',count(RememberedAccounts::list($big,$one))===8);
proofCheck('newest_retained',RememberedAccounts::verify($big,$one,10)!==null);
proofCheck('oldest_evicted',RememberedAccounts::verify($big,$one,1)===null);
echo "REMEMBERED_ACCOUNT_TESTS=".($pass+$fail)." FAILED=$fail\n";
exit($fail?1:0);
