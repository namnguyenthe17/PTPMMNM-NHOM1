<?php
declare(strict_types=1);
/** Immutable published snapshots; edits only allowed on drafts. Restore creates a NEW published snapshot. */
final class VersionService {
    public static function ensure(array &$db): void {
        foreach (['versions','release_history','decision_logs','cohort_memberships','project_memberships'] as $name) $db[$name] ??= [];
        $db['active_versions'] ??= [];
        $db['api_key_hash'] ??= '';
        foreach ($db['projects'] as $project) {
            foreach (Policy::ENVS as $env) {
                $key = self::key((int)$project['id'], $env);
                if (isset($db['active_versions'][$key])) continue;
                $id = Store::next($db, 'versions');
                $db['versions'][] = [
                    'id'=>$id, 'project_id'=>$project['id'], 'environment'=>$env,
                    'number'=>self::nextNumber($db,(int)$project['id'],$env), 'status'=>'published',
                    'source_version_id'=>null, 'flags'=>self::fromConfig($db,(int)$project['id'],$env),
                    'reason'=>'Chuyển dữ liệu cấu hình cũ thành phiên bản đầu tiên',
                    'created_by'=>null, 'created_at'=>date('c'), 'published_at'=>date('c')
                ];
                $db['active_versions'][$key] = $id;
                $db['release_history'][] = ['id'=>Store::next($db,'release_history'),'project_id'=>$project['id'],'environment'=>$env,'version_id'=>$id,'action'=>'initial','previous_version_id'=>null,'actor_id'=>null,'reason'=>'Khởi tạo phiên bản','at'=>date('c')];
            }
        }
    }
    private static function key(int $projectId,string $environment): string {return $projectId.':'.$environment;}
    private static function nextNumber(array $db,int $projectId,string $env): int {
        $max=0;foreach($db['versions'] as $v)if($v['project_id']===$projectId&&$v['environment']===$env)$max=max($max,(int)$v['number']);return $max+1;
    }
    private static function fromConfig(array $db,int $projectId,string $env): array {
        $flags=[];
        foreach ($db['features'] as $f) {
            if ((int)$f['project_id']!==$projectId) continue;
            $config=null;
            foreach($db['configs'] as $c) if((int)$c['feature_id']===(int)$f['id']&&$c['environment']===$env){$config=$c;break;}
            $flags[]=['feature_id'=>(int)$f['id'],'enabled'=>(bool)($config['enabled']??false),'percentage'=>100,'group_id'=>$config['group_id']??null,'allow_users'=>[],'deny_users'=>[]];
        }
        return $flags;
    }
    public static function get(array $db,int $versionId): array {
        foreach($db['versions']??[] as $v)if((int)$v['id']===$versionId)return $v;
        throw new DomainException('Phiên bản không tồn tại');
    }
    public static function active(array $db,int $projectId,string $env): array {
        $id=$db['active_versions'][self::key($projectId,$env)]??null;
        if ($id===null) throw new DomainException('Môi trường chưa có bản phát hành');
        return self::get($db,(int)$id);
    }
    public static function createDraft(array &$db,int $projectId,string $env,int $actorId,string $reason): int {
        self::ensure($db);
        $source=self::active($db,$projectId,$env);
        $id=Store::next($db,'versions');
        $db['versions'][]=['id'=>$id,'project_id'=>$projectId,'environment'=>$env,'number'=>self::nextNumber($db,$projectId,$env),'status'=>'draft','source_version_id'=>$source['id'],'flags'=>$source['flags'],'reason'=>mb_substr(trim($reason),0,500),'created_by'=>$actorId,'created_at'=>date('c'),'published_at'=>null];
        return $id;
    }
    public static function updateDraft(array &$db,int $versionId,int $featureId,array $changes): void {
        $version = self::get($db,$versionId);
        if ($version['status']!=='draft') throw new DomainException('Bản đã phát hành là bất biến. Hãy tạo nháp mới.');
        $feature=Policy::find($db['features'],$featureId);
        if (!$feature || (int)$feature['project_id']!==(int)$version['project_id']) throw new DomainException('Tính năng không thuộc dự án');
        $percentage=filter_var($changes['percentage']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100]]);
        if ($percentage===false) throw new DomainException('Tỷ lệ phải nằm trong khoảng 0–100');
        $groupId=$changes['group_id']??null;
        if($groupId!==null){$group=Policy::find($db['groups'],(int)$groupId);if(!$group||(int)$group['project_id']!==(int)$version['project_id'])throw new DomainException('Nhóm không thuộc dự án');}
        $allow=self::userList($changes['allow_users']??[]);
        $deny=self::userList($changes['deny_users']??[]);
        if(array_intersect($allow,$deny))throw new DomainException('Một người không thể vừa được cho phép vừa bị chặn');
        foreach($db['versions'] as &$v) {
            if($v['id']!==$versionId)continue;
            foreach($v['flags'] as &$rule) {
                if((int)$rule['feature_id']!==$featureId)continue;
                $rule['enabled']=(bool)($changes['enabled']??false);
                $rule['percentage']=(int)$percentage;
                $rule['group_id']=$groupId;
                $rule['allow_users']=$allow;
                $rule['deny_users']=$deny;
                return;
            }
            $v['flags'][]=['feature_id'=>$featureId,'enabled'=>(bool)($changes['enabled']??false),'percentage'=>(int)$percentage,'group_id'=>$groupId,'allow_users'=>$allow,'deny_users'=>$deny];
            return;
        }
        throw new DomainException('Không tìm thấy bản nháp');
    }
    public static function userList(mixed $value): array {
        if (is_string($value)) $value=preg_split('/[\s,;]+/',trim($value),-1,PREG_SPLIT_NO_EMPTY);
        if(!is_array($value)||count($value)>1000) throw new DomainException('Danh sách người dùng sai định dạng hoặc quá dài');
        $res=[];
        foreach ($value as $item) {
            if(!is_string($item)||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$item))throw new DomainException('Mã người dùng không hợp lệ');
            $res[$item]=true;
        }
        return array_keys($res);
    }
    public static function publish(array &$db,int $versionId,int $actorId,string $reason): void {
        self::ensure($db);
        $version=self::get($db,$versionId);
        if($version['status']!=='draft')throw new DomainException('Chỉ phát hành bản nháp');
        if(mb_strlen(trim($reason))<5)throw new DomainException('Cần lý do phát hành ít nhất 5 ký tự');
        $key=self::key((int)$version['project_id'],$version['environment']);
        $previous=$db['active_versions'][$key]??null;
        // Prevent stale draft overwriting changes released after draft was created.
        if ($version['source_version_id']!==$previous) throw new DomainException('Bản nháp đã cũ, cần tạo nháp từ bản mới nhất');
        self::checkSnapshot($db,$version);
        foreach($db['versions'] as &$v)if($v['id']===$versionId){$v['status']='published';$v['published_at']=date('c');$v['reason']=trim($reason);break;}unset($v);
        $db['active_versions'][$key]=$versionId;
        $db['release_history'][]=['id'=>Store::next($db,'release_history'),'project_id'=>$version['project_id'],'environment'=>$version['environment'],'version_id'=>$versionId,'previous_version_id'=>$previous,'action'=>'publish','actor_id'=>$actorId,'reason'=>trim($reason),'at'=>date('c')];
        self::syncLegacyConfigs($db,self::get($db,$versionId));
    }
    public static function rollback(array &$db,int $sourceId,int $actorId,string $reason): int {
        self::ensure($db);
        $source=self::get($db,$sourceId);
        if($source['status']!=='published')throw new DomainException('Chỉ khôi phục từ phiên bản đã phát hành');
        if(mb_strlen(trim($reason))<5)throw new DomainException('Cần lý do khôi phục');
        $key=self::key((int)$source['project_id'],$source['environment']);
        $previous=$db['active_versions'][$key]??null;
        $id=Store::next($db,'versions');
        $copy=$source;
        $copy['id']=$id;$copy['number']=self::nextNumber($db,(int)$source['project_id'],$source['environment']);
        $copy['status']='published';$copy['source_version_id']=$sourceId;
        $copy['created_by']=$actorId;$copy['created_at']=date('c');$copy['published_at']=date('c');$copy['reason']=trim($reason);
        self::checkSnapshot($db,$copy);
        $db['versions'][]=$copy;
        $db['active_versions'][$key]=$id;
        $db['release_history'][]=['id'=>Store::next($db,'release_history'),'project_id'=>$source['project_id'],'environment'=>$source['environment'],'version_id'=>$id,'previous_version_id'=>$previous,'restored_from'=>$sourceId,'action'=>'rollback','actor_id'=>$actorId,'reason'=>trim($reason),'at'=>date('c')];
        self::syncLegacyConfigs($db,$copy);
        return $id;
    }
    public static function publishCurrent(array &$db,int $projectId,string $env,int $actorId,string $reason,?int $changedFeatureId=null): int {
        self::ensure($db);
        $previous=self::active($db,$projectId,$env);
        $id=Store::next($db,'versions');
        $new=$previous;
        $new['id']=$id;$new['number']=self::nextNumber($db,$projectId,$env);
        $new['source_version_id']=$previous['id'];$new['created_by']=$actorId;
        $new['created_at']=date('c');$new['published_at']=date('c');$new['reason']=$reason;
        $new['flags']=$previous['flags'];
        $current=self::fromConfig($db,$projectId,$env);
        $seen=[];
        foreach($new['flags'] as &$rule){
            $seen[$rule['feature_id']]=true;
            if($changedFeatureId!==null&&(int)$rule['feature_id']===$changedFeatureId){
                foreach($current as $source)if($source['feature_id']===$changedFeatureId){
                    $rule['enabled']=$source['enabled'];$rule['group_id']=$source['group_id'];break;
                }
            }
        }
        unset($rule);
        foreach($current as $source)if(!isset($seen[$source['feature_id']]))$new['flags'][]=$source;
        self::checkSnapshot($db,$new);
        $db['versions'][]=$new;
        $db['active_versions'][self::key($projectId,$env)]=$id;
        $db['release_history'][]=['id'=>Store::next($db,'release_history'),'project_id'=>$projectId,'environment'=>$env,'version_id'=>$id,'previous_version_id'=>$previous['id'],'action'=>'publish','actor_id'=>$actorId,'reason'=>$reason,'at'=>date('c')];
        return $id;
    }
    private static function syncLegacyConfigs(array &$db,array $version): void {
        foreach($version['flags'] as $f) {
            $found=false;
            foreach($db['configs'] as &$c)if((int)$c['feature_id']===(int)$f['feature_id']&&$c['environment']===$version['environment']){
                $c['enabled']=$f['enabled'];$c['group_id']=$f['group_id'];$c['updated_at']=date('c');$found=true;break;
            }
            unset($c);
            if(!$found)$db['configs'][]=['id'=>Store::next($db,'configs'),'feature_id'=>$f['feature_id'],'environment'=>$version['environment'],'enabled'=>$f['enabled'],'group_id'=>$f['group_id'],'updated_at'=>date('c')];
        }
    }
    private static function checkSnapshot(array $db,array $version): void {
        if(!in_array($version['environment'],Policy::ENVS,true))throw new DomainException('Môi trường không hợp lệ');
        $seen=[];
        foreach($version['flags'] as $rule){
            $fid=(int)$rule['feature_id'];
            $f=Policy::find($db['features'],$fid);
            if(!$f||(int)$f['project_id']!==(int)$version['project_id']||isset($seen[$fid]))throw new DomainException('Tính năng trong phiên bản không hợp lệ');
            $seen[$fid]=true;
            if(!is_bool($rule['enabled'])||!is_int($rule['percentage'])||$rule['percentage']<0||$rule['percentage']>100)throw new DomainException('Trạng thái hoặc tỷ lệ sai');
            $gid=$rule['group_id'];if($gid!==null){$g=Policy::find($db['groups'],(int)$gid);if(!$g||(int)$g['project_id']!==(int)$version['project_id'])throw new DomainException('Nhóm thuộc sai dự án');}
            self::userList($rule['allow_users']);self::userList($rule['deny_users']);
        }
    }
    public static function diff(array $before,array $after,array $db): array {
        $old=[];$new=[];
        foreach($before['flags'] as $x)$old[$x['feature_id']]=$x;
        foreach($after['flags'] as $x)$new[$x['feature_id']]=$x;
        $out=[];
        foreach(array_unique(array_merge(array_keys($old),array_keys($new))) as $id){
            $a=$old[$id]??null;$b=$new[$id]??null;
            if($a!==$b)$out[]=['feature_id'=>$id,'feature_name'=>Policy::find($db['features'],(int)$id)['name']??('#'.$id),'before'=>$a,'after'=>$b];
        }
        return $out;
    }
    public static function importDraft(array &$db,int $projectId,string $env,int $actorId,string $raw): int {
        if(strlen($raw)>150000)throw new DomainException('Tệp nhập quá lớn');
        try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new DomainException('JSON không hợp lệ');}
        if(!is_array($payload)||($payload['format']??'')!=='flagflow-v1'||(int)($payload['project_id']??0)!==$projectId||($payload['environment']??'')!==$env||!is_array($payload['flags']??null))throw new DomainException('Tệp không đúng dự án, môi trường hoặc định dạng');
        $id=self::createDraft($db,$projectId,$env,$actorId,'Nhập cấu hình JSON');
        $seen=[];
        foreach($payload['flags'] as $f){
            if(!is_array($f)||!array_key_exists('enabled',$f)||!is_bool($f['enabled'])||!array_key_exists('percentage',$f)||!is_int($f['percentage'])||!array_key_exists('feature_id',$f)||!is_int($f['feature_id']))throw new DomainException('Cấu hình tính năng sai kiểu');
            if(isset($seen[$f['feature_id']]))throw new DomainException('Tính năng lặp lại');
            $seen[$f['feature_id']]=true;
            $g=$f['group_id']??null;
            if($g!==null&&!is_int($g))throw new DomainException('Mã nhóm sai kiểu');
            self::updateDraft($db,$id,$f['feature_id'],['enabled'=>$f['enabled'],'percentage'=>$f['percentage'],'group_id'=>$g,'allow_users'=>$f['allow_users']??[],'deny_users'=>$f['deny_users']??[]]);
        }
        return $id;
    }
    public static function exportJson(array $version): string {
        return json_encode(['format'=>'flagflow-v1','project_id'=>$version['project_id'],'environment'=>$version['environment'],'flags'=>$version['flags']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
}
