<?php
declare(strict_types=1);
final class ApiService {
    public static function evaluate(array $db,string $projectKey,string $featureKey,string $env,string $userCode,?int $versionId=null): array {
        if(!in_array($env,Policy::ENVS,true))throw new DomainException('Môi trường không hợp lệ');
        $project=null;
        foreach($db['projects'] as $p)if($p['key']===$projectKey){$project=$p;break;}
        if(!$project)throw new DomainException('Không tìm thấy dự án');
        $feature=null;
        foreach($db['features'] as $f)if($f['project_id']===$project['id']&&$f['key']===$featureKey){$feature=$f;break;}
        if(!$feature)throw new DomainException('Không tìm thấy tính năng');
        $version=$versionId===null?VersionService::active($db,(int)$project['id'],$env):VersionService::get($db,$versionId);
        if($version['status']!=='published'||$version['environment']!==$env||(int)$version['project_id']!==(int)$project['id'])throw new DomainException('Phiên bản không được phép sử dụng');
        $decision=FlagEngine::evaluate($db,$version,(int)$feature['id'],$userCode);
        return ['project'=>$projectKey,'environment'=>$env,'feature'=>$featureKey,'user_code'=>$userCode,'version'=>$version['id'],'version_number'=>$version['number'],'enabled'=>$decision['enabled'],'reason'=>$decision['reason'],'bucket'=>$decision['bucket']];
    }
}
