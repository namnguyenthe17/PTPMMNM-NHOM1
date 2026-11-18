<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function api_response(int $code,array $body): never {
    http_response_code($code);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
if($_SERVER['REQUEST_METHOD']!=='GET')api_response(405,['error'=>'method-not-allowed']);
$key=(string)($_SERVER['HTTP_X_API_KEY']??'');
try{$db=store()->read();}catch(Throwable){api_response(503,['error'=>'unavailable']);}
if(empty($db['api_key_hash'])||strlen($key)>256||!hash_equals($db['api_key_hash'],hash('sha256',$key)))api_response(401,['error'=>'unauthorized']);
$project=(string)($_GET['project']??'');$feature=(string)($_GET['feature']??'');
$environment=(string)($_GET['env']??'');$userCode=(string)($_GET['user']??'');
if(!preg_match('/^[a-z][a-z0-9_]{2,39}$/D',$project)||!preg_match('/^[a-z][a-z0-9_]{2,39}$/D',$feature)||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$userCode))api_response(400,['error'=>'invalid-parameters']);
$versionId=null;
if(isset($_GET['version'])&&$_GET['version']!==''){
    $versionId=filter_var($_GET['version'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($versionId===false)api_response(400,['error'=>'invalid-version']);
}
try{
    $decision=ApiService::evaluate($db,$project,$feature,$environment,$userCode,$versionId);
    // Fail-safe logging: decision must succeed even if audit write cannot complete.
    try{store()->transaction(function(array &$state)use($decision){
        $state['decision_logs']??=[];
        $state['decision_logs'][]=['id'=>Store::next($state,'decision_logs'),'project'=>$decision['project'],'environment'=>$decision['environment'],'feature'=>$decision['feature'],'user_code'=>$decision['user_code'],'version'=>$decision['version'],'enabled'=>$decision['enabled'],'reason'=>$decision['reason'],'at'=>date('c')];
        if(count($state['decision_logs'])>2000)$state['decision_logs']=array_slice($state['decision_logs'],-2000);
    });}catch(Throwable){error_log('Could not write API decision log');}
    api_response(200,$decision);
}catch(DomainException $e){api_response(404,['error'=>'config-not-found']);}
catch(Throwable $e){error_log('Feature API error: '.$e->getMessage());api_response(503,['error'=>'unavailable']);}
