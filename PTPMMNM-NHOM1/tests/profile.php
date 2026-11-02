<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$passed=0;$failed=0;
function verifyProfile(string $label,bool $condition):void {global $passed,$failed;echo ($condition?'PASS ':'FAIL ').$label."\n";$condition?$passed++:$failed++;}
$db=['users'=>[['id'=>1,'username'=>'root','name'=>'Admin','role'=>'admin','password_hash'=>password_hash('old-example-123',PASSWORD_DEFAULT)],['id'=>2,'username'=>'dev','name'=>'Developer','role'=>'developer','password_hash'=>password_hash('dev-example-123',PASSWORD_DEFAULT)]]];
ProfileService::updateInfo($db,1,'Admin Mới','admin@example.test','Người quản lý');
verifyProfile('update-own-profile',$db['users'][0]['name']==='Admin Mới'&&$db['users'][0]['email']==='admin@example.test');
verifyProfile('other-account-unchanged',$db['users'][1]['name']==='Developer');
verifyProfile('role-remains-admin',$db['users'][0]['role']==='admin');
try { ProfileService::updateInfo($db,1,'A','admin@example.test','');$bad=false; } catch(DomainException) {$bad=true;} verifyProfile('reject-short-name',$bad);
try { ProfileService::updateInfo($db,1,'Valid Name','bad-email','');$bad=false; } catch(DomainException) {$bad=true;} verifyProfile('reject-invalid-email',$bad);
try { ProfileService::changePassword($db,1,'wrong','new-example-123','new-example-123');$bad=false; } catch(DomainException) {$bad=true;} verifyProfile('reject-wrong-current-password',$bad);
try { ProfileService::changePassword($db,1,'old-example-123','new-example-123','mismatch');$bad=false; } catch(DomainException) {$bad=true;} verifyProfile('reject-mismatched-confirmation',$bad);
ProfileService::changePassword($db,1,'old-example-123','new-example-123','new-example-123');
verifyProfile('password-hashed-and-changed',password_verify('new-example-123',$db['users'][0]['password_hash'])&&!password_verify('old-example-123',$db['users'][0]['password_hash']));
verifyProfile('invalid-avatar-url-rejected',ProfileService::avatarUrl('/files/../../index.php')==='');
$url='/uploads/avatars/'.str_repeat('a',32).'.webp';
verifyProfile('safe-avatar-url-accepted',ProfileService::avatarUrl($url)===$url);
$old=ProfileService::updateAvatar($db,1,$url);
verifyProfile('avatar-update',$old===''&&$db['users'][0]['avatar']===$url);
$old=ProfileService::removeAvatar($db,1);
verifyProfile('avatar-remove',$old===$url&&$db['users'][0]['avatar']==='');
echo 'PROFILE_TESTS='.($passed+$failed)." FAILED=$failed\n";
exit($failed>0?1:0);
