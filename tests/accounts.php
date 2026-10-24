<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$base=['users'=>[]];$ok=0;$fail=0;
function check(string $name, bool $result):void {global $ok,$fail;echo ($result?'PASS ':'FAIL ').$name."\n";if($result)$ok++;else $fail++;}
$id=AccountService::create($base,'tester_new','Tester Mới','pass-Test-2026','tester');
check('create_test_user',$id===1&&$base['users'][0]['role']==='tester');
check('hash_is_secure',password_verify('pass-Test-2026',$base['users'][0]['password_hash']));
try{AccountService::create($base,'tester_new','Trùng tên','pass-Test-2026','tester');check('reject_duplicate',false);}catch(DomainException $e){check('reject_duplicate',true);}
try{AccountService::create($base,'new_admin','New Admin','pass-Test-2026','owner');check('reject_bad_role',false);}catch(DomainException $e){check('reject_bad_role',true);}
try{AccountService::create($base,'short_pw','Tester Mới','short','tester');check('reject_short_password',false);}catch(DomainException $e){check('reject_short_password',true);}
AccountService::changePassword($base,$id,'new-Pass-2026');check('password_reset',password_verify('new-Pass-2026',$base['users'][0]['password_hash'])&&!password_verify('pass-Test-2026',$base['users'][0]['password_hash']));
check('role_options',AccountService::ROLES===['admin','developer','tester']);
echo "ACCOUNT_TESTS=".($ok+$fail)." FAILED=$fail\n";exit($fail?1:0);
