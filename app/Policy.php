<?php
declare(strict_types=1);
final class Policy {
    public const ENVS=['development','beta','production'];
    public static function canRequest(string $role,string $environment): bool {
        return in_array($role,['developer','admin'],true) && in_array($environment,['beta','production'],true);
    }
    public static function canDirectToggle(string $role,string $environment): bool {
        return in_array($role,['developer','admin'],true) && $environment==='development';
    }
    public static function canApprove(array $actor,array $request): bool {
        return $actor['role']==='admin' && $actor['id']!==$request['requested_by'] && $request['status']==='pending';
    }
    public static function enabledFor(array $db,int $featureId,string $env,int $userId): bool {
        $feature=self::find($db['features'],$featureId);
        if (!$feature || !in_array($env,self::ENVS,true)) return false;
        $config=null; foreach($db['configs'] as $row) if($row['feature_id']===$featureId && $row['environment']===$env) $config=$row;
        if (!$config || !$config['enabled']) return false;
        if ($config['group_id']===null) return true;
        foreach($db['memberships'] as $member) if($member['user_id']===$userId && $member['group_id']===$config['group_id']) return true;
        return false;
    }
    public static function find(array $rows,int $id): ?array { foreach($rows as $row) if($row['id']===$id) return $row; return null; }
}
