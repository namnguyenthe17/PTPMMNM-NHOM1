<?php
declare(strict_types=1);
/** Development -> Tester -> Admin. Saved inside the existing JSON/MySQL aggregate. */
final class ReviewWorkflow {
    public static function normalize(string $status): string {
        return $status === 'pending' ? 'pending_test' : $status;
    }
    public static function label(string $status): string {
        return match(self::normalize($status)) {
            'pending_test'=>'Chờ Tester kiểm thử',
            'tester_approved'=>'Tester đạt · Chờ Admin',
            'tester_rejected'=>'Tester trả về Dev',
            'approved'=>'Đã phát hành',
            'rejected'=>'Admin từ chối',
            default=>$status,
        };
    }
    public static function color(string $status): string {
        return match(self::normalize($status)) {
            'approved'=>'green','tester_approved'=>'blue',
            'rejected','tester_rejected'=>'red',default=>'orange'
        };
    }
    public static function pending(string $status): bool {
        return in_array(self::normalize($status),['pending_test','tester_approved'],true);
    }
    public static function projectIsPublished(array $project): bool {
        return ($project['publication_status']??'published')==='published';
    }
    private static function reviewFingerprint(array $db,string $type,int $targetId): string {
        if($type==='version') {
            $version=VersionService::get($db,$targetId);
            return hash('sha256',json_encode([$version['project_id'],$version['environment'],$version['source_version_id'],$version['flags']],JSON_THROW_ON_ERROR));
        }
        $project=Policy::find($db['projects'],$targetId);
        if(!$project) throw new DomainException('Dự án không tồn tại');
        $features=array_values(array_filter($db['features']??[],fn($f)=>(int)$f['project_id']===$targetId));
        $configs=array_values(array_filter($db['configs']??[],fn($c)=>in_array((int)$c['feature_id'],array_map(fn($f)=>(int)$f['id'],$features),true)));
        return hash('sha256',json_encode([$project['name'],$project['key'],$project['description'],$features,$configs],JSON_THROW_ON_ERROR));
    }
    public static function isDraftLocked(array $db,int $versionId): bool {
        foreach($db['release_submissions']??[] as $entry) {
            if($entry['type']==='version' && (int)$entry['target_id']===$versionId && self::pending($entry['status']))return true;
        }
        return false;
    }
    public static function submissionFor(array $db,string $type,int $targetId): ?array {
        foreach(array_reverse($db['release_submissions']??[]) as $entry)if($entry['type']===$type && (int)$entry['target_id']===$targetId)return $entry;
        return null;
    }
    public static function submit(array &$db,array $actor,string $type,int $targetId,string $reason): int {
        if(($actor['role']??'')!=='developer')throw new DomainException('Chỉ Developer được gửi kiểm thử');
        if(!in_array($type,['project','version'],true))throw new DomainException('Loại gửi kiểm thử không hợp lệ');
        if(mb_strlen(trim($reason))<5 || mb_strlen($reason)>500)throw new DomainException('Cần mô tả kiểm thử từ 5 đến 500 ký tự');
        if($type==='project') {
            $p=Policy::find($db['projects'],$targetId);
            if(!$p || self::projectIsPublished($p))throw new DomainException('Chỉ gửi dự án chưa phát hành');
            if(!array_filter($db['features']??[],fn($f)=>(int)$f['project_id']===$targetId))throw new DomainException('Hãy tạo ít nhất một tính năng trước khi gửi dự án để Tester kiểm tra');
            $projectId=$targetId;$env='production';
        }else{
            $v=VersionService::get($db,$targetId);
            if($v['status']!=='draft')throw new DomainException('Chỉ gửi bản nháp cho Tester');
            $projectId=(int)$v['project_id'];$env=(string)$v['environment'];
            $active=VersionService::active($db,$projectId,$env);
            if((int)$v['source_version_id']!==(int)$active['id'])throw new DomainException('Bản nháp đã cũ; hãy tạo lại từ bản hiện hành');
        }
        ProjectAccess::requireEdit($db,$actor,$projectId);
        $current=self::submissionFor($db,$type,$targetId);
        if($current && self::pending($current['status'])){
            if(hash_equals($current['fingerprint'],self::reviewFingerprint($db,$type,$targetId)))throw new DomainException('Đã gửi Tester, vui lòng chờ xử lý');
            foreach($db['release_submissions'] as &$old)if((int)$old['id']===(int)$current['id']){$old['status']='superseded';break;}unset($old);
        }
        $db['release_submissions']??=[];
        $id=Store::next($db,'release_submissions');
        $db['release_submissions'][]=[
            'id'=>$id,'type'=>$type,'target_id'=>$targetId,'project_id'=>$projectId,'environment'=>$env,
            'status'=>'pending_test','reason'=>trim($reason),'requested_by'=>(int)$actor['id'],
            'fingerprint'=>self::reviewFingerprint($db,$type,$targetId),
            'tester_by'=>null,'tester_note'=>'','tested_at'=>null,
            'reviewed_by'=>null,'review_note'=>'','reviewed_at'=>null,'created_at'=>date('c')
        ];
        return $id;
    }
    public static function testerReview(array &$db,array $actor,string $type,int $id,string $decision,string $note,array $checks): void {
        if(($actor['role']??'')!=='tester')throw new DomainException('Chỉ Tester được xác nhận kiểm thử');
        if(!in_array($decision,['tester_approved','tester_rejected'],true))throw new DomainException('Kết quả kiểm thử không hợp lệ');
        if(mb_strlen(trim($note))<5 || mb_strlen($note)>500)throw new DomainException('Ghi kết quả kiểm thử tối thiểu 5 ký tự');
        if($decision==='tester_approved' && (!in_array('functional',$checks,true) || !in_array('permissions',$checks,true) || !in_array('errors',$checks,true)))throw new DomainException('Phải xác nhận 3 nội dung kiểm thử trước khi đánh dấu đạt');
        if($type==='feature') {
            foreach($db['requests'] as &$r) {
                if((int)$r['id']!==$id)continue;
                $f=Policy::find($db['features'],(int)$r['feature_id']);
                if(!$f)throw new DomainException('Tính năng không tồn tại');
                ProjectAccess::requireView($db,$actor,(int)$f['project_id']);
                if((int)$r['requested_by']===(int)$actor['id'])throw new DomainException('Không được tự kiểm thử thay đổi của mình');
                if(self::normalize($r['status'])!=='pending_test')throw new DomainException('Yêu cầu đã được xử lý');
                self::checkFeatureBaseline($db,$r);
                $r['status']=$decision;$r['tester_by']=(int)$actor['id'];$r['tester_note']=trim($note);
                $r['tested_at']=date('c');$r['test_checks']=$checks;
                return;
            }
            unset($r);
        }elseif(in_array($type,['project','version'],true)){
            $db['release_submissions']??=[];
            foreach($db['release_submissions'] as &$r) {
                if((int)$r['id']!==$id || $r['type']!==$type)continue;
                ProjectAccess::requireView($db,$actor,(int)$r['project_id']);
                if(self::normalize($r['status'])!=='pending_test')throw new DomainException('Yêu cầu đã được xử lý');
                self::assertFresh($db,$r);
                $r['status']=$decision;$r['tester_by']=(int)$actor['id'];$r['tester_note']=trim($note);
                $r['tested_at']=date('c');$r['test_checks']=$checks;
                return;
            }
            unset($r);
        }
        throw new DomainException('Không tìm thấy yêu cầu');
    }
    public static function checkFeatureBaseline(array $db,array $r): void {
        if(!isset($r['basis_version_id']))return; // Backwards compatible with legacy unreviewed requests
        $f=Policy::find($db['features'],(int)$r['feature_id']);
        if(!$f)throw new DomainException('Không tìm thấy tính năng');
        $active=VersionService::active($db,(int)$f['project_id'],(string)$r['environment']);
        if((int)$active['id']!==(int)$r['basis_version_id'])throw new DomainException('Cấu hình môi trường đã thay đổi; Developer cần gửi lại kiểm thử');
    }
    public static function assertFresh(array $db,array $request): void {
        if(!hash_equals($request['fingerprint'],self::reviewFingerprint($db,$request['type'],(int)$request['target_id'])))throw new DomainException('Nội dung đã thay đổi sau khi gửi Tester; cần gửi lại bản mới');
        if($request['type']==='version') {
            $v=VersionService::get($db,(int)$request['target_id']);
            $active=VersionService::active($db,(int)$v['project_id'],(string)$v['environment']);
            if($v['source_version_id']!==$active['id'])throw new DomainException('Phiên bản gốc đã thay đổi, yêu cầu kiểm thử lại');
        }
    }
    public static function assertVersionReady(array $db,int $versionId): void {
        $request=self::submissionFor($db,'version',$versionId);
        if(!$request || $request['status']!=='tester_approved' || empty($request['tester_by']))throw new DomainException('Phiên bản chưa được Tester kiểm thử đạt');
        self::assertFresh($db,$request);
    }
    public static function adminResolve(array &$db,array $actor,string $type,int $id,string $decision,string $note): array {
        if(($actor['role']??'')!=='admin')throw new DomainException('Chỉ Admin được phát hành');
        if(!in_array($decision,['approved','rejected'],true))throw new DomainException('Quyết định không hợp lệ');
        if(mb_strlen(trim($note))<5 || mb_strlen($note)>500)throw new DomainException('Nhập lý do ít nhất 5 ký tự');
        $db['release_submissions']??=[];
        foreach($db['release_submissions'] as &$request) {
            if((int)$request['id']!==$id || $request['type']!==$type)continue;
            if($request['status']!=='tester_approved' || empty($request['tester_by']))throw new DomainException('Tester chưa xác nhận đạt, Admin không thể phát hành');
            if((int)$request['requested_by']===(int)$actor['id'])throw new DomainException('Người gửi không được tự phát hành');
            self::assertFresh($db,$request);
            $projectId=(int)$request['project_id'];
            if($decision==='approved'){
                if($type==='project') {
                    foreach($db['projects'] as &$p)if((int)$p['id']===$projectId){$p['publication_status']='published';$p['published_at']=date('c');break;}
                    unset($p);
                }elseif($type==='version'){
                    VersionService::publish($db,(int)$request['target_id'],(int)$actor['id'],trim($note));
                }
            }
            $request['status']=$decision;$request['reviewed_by']=(int)$actor['id'];
            $request['review_note']=trim($note);$request['reviewed_at']=date('c');
            return $request;
        }
        throw new DomainException('Không tìm thấy bản chờ phát hành');
    }
}
