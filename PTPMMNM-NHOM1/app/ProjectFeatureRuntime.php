<?php
declare(strict_types=1);

/**
 * Reusable project workspace for features without a dedicated PHP module.
 * This is a generic record workflow; it must not be represented as a full
 * implementation of arbitrary domain-specific business logic.
 */
final class ProjectFeatureRuntime {
    public static function find(array $db, int $projectId, int $featureId): array {
        $project = Policy::find($db['projects'] ?? [], $projectId);
        $feature = Policy::find($db['features'] ?? [], $featureId);
        if (!$project || !$feature || (int)$feature['project_id'] !== $projectId) {
            throw new DomainException('Không tìm thấy tính năng trong dự án này');
        }
        return [$project, $feature];
    }

    public static function permission(array $db, array $actor, int $projectId, int $featureId, string $environment): array {
        [$project, $feature] = self::find($db, $projectId, $featureId);
        if (!ReviewWorkflow::projectIsPublished($project)) {
            throw new DomainException('Dự án chưa được Admin phát hành');
        }
        ProjectAccess::requireView($db, $actor, $projectId);
        if (!in_array($environment, Policy::ENVS, true)) {
            throw new DomainException('Môi trường không hợp lệ');
        }
        if (($actor['role'] ?? '') === 'tester' && $environment !== 'beta') {
            throw new DomainException('Tester chỉ sử dụng môi trường Beta');
        }
        try {
            $release = VersionService::active($db, $projectId, $environment);
            $decision = FlagEngine::evaluate($db, $release, $featureId, CommunityService::userCode($actor));
        } catch (Throwable) {
            throw new DomainException('Tính năng chưa có bản cấu hình hợp lệ');
        }
        if (!$decision['enabled']) {
            throw new DomainException('Tính năng chưa được cấp quyền cho tài khoản này');
        }
        return [$project, $feature, $release];
    }

    public static function create(array &$db, array $actor, int $projectId, int $featureId, string $env, string $title, string $description): int {
        self::permission($db, $actor, $projectId, $featureId, $env);
        $title = trim($title);
        $description = trim($description);
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            throw new DomainException('Tiêu đề phải dài từ 2 đến 120 ký tự');
        }
        if (mb_strlen($description) > 2000) {
            throw new DomainException('Nội dung tối đa 2.000 ký tự');
        }
        $db['project_feature_records'] ??= [];
        $id = Store::next($db, 'project_feature_records');
        $db['project_feature_records'][] = [
            'id'=>$id, 'project_id'=>$projectId, 'feature_id'=>$featureId,
            'created_by'=>(int)$actor['id'], 'title'=>$title, 'description'=>$description,
            'status'=>'open', 'created_at'=>date(DATE_ATOM), 'updated_at'=>date(DATE_ATOM)
        ];
        return $id;
    }

    public static function updateStatus(array &$db, array $actor, int $recordId, int $projectId, int $featureId, string $env, string $status): void {
        if (!in_array($status, ['open','done'], true)) throw new DomainException('Trạng thái không hợp lệ');
        $db['project_feature_records'] ??= [];
        foreach ($db['project_feature_records'] as &$record) {
            if ((int)$record['id'] !== $recordId) continue;
            if ((int)$record['project_id'] !== $projectId || (int)$record['feature_id'] !== $featureId) {
                throw new DomainException('Bản ghi không thuộc dự án/tính năng đã chọn');
            }
            self::permission($db, $actor, $projectId, $featureId, $env);
            if ((int)$record['created_by'] !== (int)$actor['id']) {
                throw new DomainException('Bạn chỉ được thay đổi bản ghi của chính mình');
            }
            $record['status']=$status;
            $record['updated_at']=date(DATE_ATOM);
            return;
        }
        unset($record);
        throw new DomainException('Không tìm thấy bản ghi');
    }

    public static function records(array $db, array $actor, int $projectId, int $featureId): array {
        $result = [];
        foreach ($db['project_feature_records'] ?? [] as $row) {
            if ((int)$row['project_id'] !== $projectId || (int)$row['feature_id'] !== $featureId) continue;
            if (($actor['role'] ?? '') === 'tester' && (int)$row['created_by'] !== (int)$actor['id']) continue;
            $result[]=$row;
        }
        usort($result, static fn($a, $b)=>(int)$b['id'] <=> (int)$a['id']);
        return $result;
    }

    /** Dedicated modules only exist for keys with implemented PHP workflows. */
    public static function dedicatedSlug(array $feature): ?string {
        return match ((string)($feature['key'] ?? '')) {
            'new_registration' => 'register',
            'smart_waitlist' => 'waitlist',
            'dark_theme' => 'dark',
            'advanced_search' => 'search',
            'push_notification' => 'notifications',
            'edit_profile', 'profile_edit', 'update_profile', 'sua_thong_tin_ca_nhan' => 'profile',
            default => null
        };
    }
}
