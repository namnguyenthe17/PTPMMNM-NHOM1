<?php
declare(strict_types=1);
/**
 * Feature-flag decision is pure and deterministic. Priority:
 * global disable > deny list > allow list > optional cohort gate > percentage.
 * An allow list bypasses the cohort gate and percentage, but never global disable.
 */
final class FlagEngine {
    public static function bucket(int $projectId, string $environment, int $versionId, int $featureId, string $userCode): int {
        $seed = implode('|', ['flagflow-v1', $projectId, $environment, $versionId, $featureId, $userCode]);
        return hexdec(substr(hash('sha256', $seed), 0, 8)) % 10000;
    }
    public static function evaluate(array $db, array $version, int $featureId, string $userCode): array {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $userCode)) {
            return ['enabled'=>false, 'reason'=>'invalid-user-code', 'bucket'=>null];
        }
        $rule = null;
        foreach ($version['flags'] ?? [] as $flag) {
            if ((int)($flag['feature_id'] ?? 0) === $featureId) { $rule = $flag; break; }
        }
        if (!$rule || !$rule['enabled']) return ['enabled'=>false, 'reason'=>'disabled', 'bucket'=>null];
        if (in_array($userCode, $rule['deny_users'] ?? [], true)) return ['enabled'=>false,'reason'=>'deny-list','bucket'=>null];
        if (in_array($userCode, $rule['allow_users'] ?? [], true)) return ['enabled'=>true,'reason'=>'allow-list','bucket'=>null];
        $groupId = $rule['group_id'] ?? null;
        if ($groupId !== null) {
            $member = false;
            foreach ($db['cohort_memberships'] ?? [] as $m) {
                if ((int)$m['group_id'] === (int)$groupId && $m['user_code'] === $userCode) {$member = true; break;}
            }
            if (!$member) return ['enabled'=>false, 'reason'=>'outside-cohort','bucket'=>null];
        }
        $percent = (int)($rule['percentage'] ?? 100);
        if ($percent <= 0) return ['enabled'=>false,'reason'=>'rollout-0','bucket'=>null];
        if ($percent >= 100) return ['enabled'=>true,'reason'=>'rollout-100','bucket'=>null];
        $bucket = self::bucket((int)$version['project_id'], (string)$version['environment'], (int)$version['id'], $featureId, $userCode);
        return ['enabled'=>$bucket < $percent * 100,'reason'=>'percentage','bucket'=>$bucket];
    }
}
