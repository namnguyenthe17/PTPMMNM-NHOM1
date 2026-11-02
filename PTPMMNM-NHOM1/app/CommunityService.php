<?php
declare(strict_types=1);
/**
 * CommunityPass: usable event registration and waitlist application.
 * Records live in the same JSON/MySQL transaction as feature flags.
 * No external payment, SMS, AI or push-service integration is claimed.
 */
final class CommunityService {
    public static function ensure(array &$db): void {
        foreach (['community_events','community_registrations','community_notifications','community_activity'] as $table) {
            $db[$table] ??= [];
        }
        $db['community_preferences'] ??= [];
    }

    public static function userCode(array $actor): string {
        return 'user'.str_pad((string)(int)$actor['id'], 4, '0', STR_PAD_LEFT);
    }

    public static function enabled(array $db, int $projectId, string $env, string $key, array $actor): bool {
        if (!in_array($env, Policy::ENVS, true)) return false;
        if (!ProjectAccess::canView($db,$actor,$projectId)) return false;
        $project=Policy::find($db['projects']??[],$projectId);
        if(!$project || !ReviewWorkflow::projectIsPublished($project))return false;
        $feature = null;
        foreach ($db['features']??[] as $f) {
            if ((int)$f['project_id'] === $projectId && $f['key'] === $key) {
                $feature = $f; break;
            }
        }
        if (!$feature) return false;
        try {
            $v = VersionService::active($db, $projectId, $env);
            return FlagEngine::evaluate($db,$v,(int)$feature['id'],self::userCode($actor))['enabled'] === true;
        } catch (Throwable) {
            return false; // fail closed; never grant access when release is unavailable
        }
    }

    public static function event(array $db, int $id): array {
        foreach ($db['community_events']??[] as $event) {
            if ((int)$event['id'] === $id) return $event;
        }
        throw new DomainException('Không tìm thấy sự kiện');
    }

    public static function createEvent(array &$db,array $actor,int $projectId,string $name,string $category,string $description,string $location,string $startsAt,int $capacity): int {
        self::ensure($db);
        if (($actor['role']??'')!=='developer') throw new DomainException('Chỉ Developer được tạo sự kiện');
        ProjectAccess::requireEdit($db,$actor,$projectId);
        $name=trim($name);$description=trim($description);$location=trim($location);$category=trim($category);
        if (mb_strlen($name)<5||mb_strlen($name)>120) throw new DomainException('Tên sự kiện phải từ 5 đến 120 ký tự');
        if (mb_strlen($description)>600||mb_strlen($location)<3||mb_strlen($location)>120)throw new DomainException('Mô tả hoặc địa điểm không hợp lệ');
        if (!in_array($category,['technology','community','workshop','other'],true))throw new DomainException('Danh mục sự kiện không hợp lệ');
        if ($capacity<1||$capacity>5000)throw new DomainException('Số lượng chỗ phải từ 1 đến 5.000');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$startsAt,new DateTimeZone('Asia/Ho_Chi_Minh'));
        if(!$date||$date->format('Y-m-d\TH:i')!==$startsAt)throw new DomainException('Thời gian sự kiện không hợp lệ');
        if($date->getTimestamp() < time()-3600)throw new DomainException('Không thể tạo sự kiện đã qua');
        $id=Store::next($db,'community_events');
        $db['community_events'][]=['id'=>$id,'project_id'=>$projectId,'name'=>$name,'category'=>$category,'description'=>$description,'location'=>$location,'starts_at'=>$date->format(DATE_ATOM),'capacity'=>$capacity,'created_by'=>(int)$actor['id'],'created_at'=>date(DATE_ATOM)];
        self::activity($db,'event.created', 'Đã tạo sự kiện: '.$name,(int)$actor['id'],$id);
        return $id;
    }

    public static function status(array $db,int $eventId): array {
        $confirmed=0;$waiting=0;
        foreach ($db['community_registrations']??[] as $r) {
            if ((int)$r['event_id']!==$eventId) continue;
            if ($r['status']==='confirmed') $confirmed++;
            elseif ($r['status']==='waitlisted') $waiting++;
        }
        return ['confirmed'=>$confirmed,'waitlisted'=>$waiting];
    }

    public static function registration(array $db,int $eventId,int $userId): ?array {
        foreach ($db['community_registrations']??[] as $r) {
            if ((int)$r['event_id']===$eventId && (int)$r['user_id']===$userId && in_array($r['status'],['confirmed','waitlisted'],true)) return $r;
        }
        return null;
    }

    public static function register(array &$db,array $actor,int $eventId,string $env): string {
        self::ensure($db);
        if (($actor['role']??'')!=='tester') throw new DomainException('Chỉ tài khoản Tester được đăng ký tham dự');
        if ($env !== 'beta') throw new DomainException('Tester chỉ được sử dụng môi trường Beta');
        $event=self::event($db,$eventId);
        $pid=(int)$event['project_id'];
        ProjectAccess::requireView($db,$actor,$pid);
        if(strtotime($event['starts_at']) < time())throw new DomainException('Sự kiện đã diễn ra');
        if(!self::enabled($db,$pid,$env,'new_registration',$actor))throw new DomainException('Bạn chưa được cấp tính năng Đăng ký sự kiện ở môi trường này');
        if(self::registration($db,$eventId,(int)$actor['id'])!==null)throw new DomainException('Bạn đã đăng ký hoặc đang trong danh sách chờ');
        $stats=self::status($db,$eventId);
        $confirmed=$stats['confirmed']<(int)$event['capacity'];
        if(!$confirmed && !self::enabled($db,$pid,$env,'smart_waitlist',$actor))throw new DomainException('Đã hết chỗ. Danh sách chờ chưa được bật cho tài khoản này');
        $status=$confirmed?'confirmed':'waitlisted';
        $db['community_registrations'][]=['id'=>Store::next($db,'community_registrations'),'event_id'=>$eventId,'user_id'=>(int)$actor['id'],'status'=>$status,'created_at'=>date(DATE_ATOM),'updated_at'=>date(DATE_ATOM)];
        $message=$confirmed?'Đăng ký thành công: ':'Bạn đã vào danh sách chờ: ';
        self::notify($db,(int)$actor['id'],$message.$event['name']);
        self::activity($db,'registration.'.$status,$message.$event['name'],(int)$actor['id'],$eventId);
        return $status;
    }

    public static function cancel(array &$db,array $actor,int $eventId): void {
        self::ensure($db);
        $event=self::event($db,$eventId);
        ProjectAccess::requireView($db,$actor,(int)$event['project_id']);
        if(strtotime($event['starts_at'])<time())throw new DomainException('Sự kiện đã bắt đầu, không thể hủy đăng ký');
        $oldStatus=null;
        foreach($db['community_registrations'] as &$r){
            if((int)$r['event_id']===$eventId && (int)$r['user_id']===(int)$actor['id'] && in_array($r['status'],['confirmed','waitlisted'],true)){
                $oldStatus=$r['status'];$r['status']='cancelled';$r['updated_at']=date(DATE_ATOM);break;
            }
        }
        unset($r);
        if($oldStatus===null)throw new DomainException('Không tìm thấy đăng ký của bạn');
        self::notify($db,(int)$actor['id'],'Đã hủy đăng ký: '.$event['name']);
        self::activity($db,'registration.cancelled','Đã hủy đăng ký: '.$event['name'],(int)$actor['id'],$eventId);
        // Promote oldest waitlisted registration only when a confirmed spot was freed.
        if($oldStatus==='confirmed'){
            $oldestIndex=null;
            foreach($db['community_registrations'] as $i=>$r){
                if((int)$r['event_id']===$eventId && $r['status']==='waitlisted'){
                    if($oldestIndex===null || $r['id']<$db['community_registrations'][$oldestIndex]['id'])$oldestIndex=$i;
                }
            }
            if($oldestIndex!==null){
                $next=&$db['community_registrations'][$oldestIndex];
                $next['status']='confirmed';$next['updated_at']=date(DATE_ATOM);
                self::notify($db,(int)$next['user_id'],'Đã có chỗ! Bạn được chuyển từ danh sách chờ sang đã xác nhận: '.$event['name']);
                self::activity($db,'waitlist.promoted','Đã chuyển người chờ sang xác nhận: '.$event['name'],(int)$next['user_id'],$eventId);
                unset($next);
            }
        }
    }

    public static function notify(array &$db,int $userId,string $message): void {
        self::ensure($db);
        $db['community_notifications'][]=['id'=>Store::next($db,'community_notifications'),'user_id'=>$userId,'message'=>$message,'read_at'=>null,'created_at'=>date(DATE_ATOM)];
    }

    public static function markRead(array &$db,array $actor,int $notificationId): void {
        self::ensure($db);
        foreach ($db['community_notifications'] as &$n) {
            if((int)$n['id']===$notificationId && (int)$n['user_id']===(int)$actor['id']){
                $n['read_at'] ??= date(DATE_ATOM);
                return;
            }
        }
        unset($n);
        throw new DomainException('Không tìm thấy thông báo của bạn');
    }

    public static function activity(array &$db,string $action,string $description,int $userId,int $eventId): void {
        self::ensure($db);
        $db['community_activity'][]=['id'=>Store::next($db,'community_activity'),'action'=>$action,'detail'=>$description,'user_id'=>$userId,'event_id'=>$eventId,'at'=>date(DATE_ATOM)];
    }
}
