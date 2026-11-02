<?php
declare(strict_types=1);
final class ProjectAccess {
    public static function level(array $db,array $actor,int $projectId): string {
        if(($actor['role']??'')==='admin')return 'manager';
        $levels=['viewer'=>1,'editor'=>2,'manager'=>3];$best=0;$role='none';
        foreach($db['project_memberships']??[] as $m) {
            if((int)$m['project_id']===$projectId&&(int)$m['user_id']===(int)($actor['id']??0)) {
                $current=$levels[$m['permission']??'']??0;
                if($current>$best){$role=$m['permission'];$best=$current;}
            }
        }
        return $role;
    }
    public static function canView(array $db,array $actor,int $projectId): bool {return self::level($db,$actor,$projectId)!=='none';}
    public static function canEdit(array $db,array $actor,int $projectId): bool {return in_array(self::level($db,$actor,$projectId),['editor','manager'],true);}
    public static function requireView(array $db,array $actor,int $projectId): void {if(!self::canView($db,$actor,$projectId))throw new DomainException('Bạn không có quyền xem dự án này');}
    public static function requireEdit(array $db,array $actor,int $projectId): void {if(!self::canEdit($db,$actor,$projectId))throw new DomainException('Bạn chỉ có quyền xem hoặc không thuộc dự án');}
    public static function grant(array &$db,int $projectId,int $userId,string $permission): void {
        if(!in_array($permission,['viewer','editor','manager'],true))throw new DomainException('Quyền dự án không hợp lệ');
        if(!Policy::find($db['projects'],$projectId)||!Policy::find($db['users'],$userId))throw new DomainException('Dự án/tài khoản không tồn tại');
        foreach($db['project_memberships'] as &$m)if($m['project_id']===$projectId&&$m['user_id']===$userId){$m['permission']=$permission;return;}unset($m);
        $db['project_memberships'][]=['project_id'=>$projectId,'user_id'=>$userId,'permission'=>$permission];
    }
    public static function revoke(array &$db,int $projectId,int $userId): void {
        $db['project_memberships']=array_values(array_filter($db['project_memberships']??[],fn($m)=>!((int)$m['project_id']===$projectId&&(int)$m['user_id']===$userId)));
    }
}
