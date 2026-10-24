<?php
declare(strict_types=1);
/** Nghiệp vụ tạo một tính năng và gửi đề xuất phát hành trong cùng giao dịch. */
final class FeatureSubmission {
    public static function submit(array &$db, array $actor, int $projectId, string $key, string $name, string $description, string $environment, ?int $groupId, string $reason): int {
        if (!in_array($actor['role'] ?? '', ['developer','admin'], true)) throw new DomainException('Chỉ Developer/Admin được đề xuất tính năng');
        if (!in_array($environment,['beta','production'],true)) throw new DomainException('Chỉ gửi duyệt Beta hoặc Production');
        if (!preg_match('/^[a-z][a-z0-9_]{2,39}$/',$key)) throw new DomainException('Mã tính năng không hợp lệ');
        if (mb_strlen($name)<3 || mb_strlen($name)>90 || mb_strlen($description)>300) throw new DomainException('Tên hoặc mô tả không hợp lệ');
        if (mb_strlen($reason)<5 || mb_strlen($reason)>500) throw new DomainException('Lý do từ 5 đến 500 ký tự');
        $project=Policy::find($db['projects'],$projectId);
        if (!$project) throw new DomainException('Dự án không tồn tại');
        foreach ($db['features'] as $feature) if($feature['project_id']===$projectId && $feature['key']===$key) throw new DomainException('Mã tính năng đã tồn tại trong dự án');
        if ($groupId!==null) {
            $group=Policy::find($db['groups'],$groupId);
            if (!$group || $group['project_id']!==$projectId) throw new DomainException('Nhóm thử nghiệm không thuộc dự án');
        }
        $featureId=Store::next($db,'features');
        $db['features'][]=['id'=>$featureId,'project_id'=>$projectId,'key'=>$key,'name'=>$name,'description'=>$description];
        foreach(Policy::ENVS as $env) {
            $db['configs'][]=['id'=>Store::next($db,'configs'),'feature_id'=>$featureId,'environment'=>$env,'enabled'=>$env==='development','group_id'=>null,'updated_at'=>date('c')];
        }
        $requestId=Store::next($db,'requests');
        $db['requests'][]=['id'=>$requestId,'feature_id'=>$featureId,'environment'=>$environment,'enabled'=>true,'group_id'=>$groupId,'reason'=>$reason,'status'=>'pending','requested_by'=>$actor['id'],'reviewed_by'=>null,'review_note'=>'','created_at'=>date('c'),'reviewed_at'=>null];
        return $requestId;
    }
}
