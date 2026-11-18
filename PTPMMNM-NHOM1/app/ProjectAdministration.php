<?php
declare(strict_types=1);

/** Admin-only project lifecycle operations, independent from version approval. */
final class ProjectAdministration {
    public static function update(array &$db, int $projectId, string $name, string $description): void {
        $name = trim($name);
        $description = trim($description);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 80 || mb_strlen($description) > 300) {
            throw new DomainException('Tên dự án 3–80 ký tự; mô tả không quá 300 ký tự');
        }
        foreach ($db['projects'] as &$project) {
            if ((int)$project['id'] !== $projectId) continue;
            // A pending tester review was fingerprinted against this metadata.
            foreach ($db['release_submissions'] ?? [] as $request) {
                if (($request['type']??'') === 'project' && (int)($request['target_id']??0) === $projectId
                    && ReviewWorkflow::pending((string)($request['status']??''))) {
                    throw new DomainException('Dự án đang chờ kiểm thử/phê duyệt. Hãy xử lý yêu cầu trước khi đổi tên.');
                }
            }
            $project['name'] = $name;
            $project['description'] = $description;
            return;
        }
        unset($project);
        throw new DomainException('Không tìm thấy dự án');
    }

    /** Preserve unrelated projects and users; deletion must be backed up first. */
    public static function delete(array &$db, int $projectId): void {
        $project = Policy::find($db['projects'] ?? [], $projectId);
        if (!$project) throw new DomainException('Không tìm thấy dự án');
        $featureIds = array_map('intval', array_column(array_values(array_filter($db['features']??[],
            static fn($f) => (int)$f['project_id'] === $projectId)), 'id'));
        $groupIds = array_map('intval', array_column(array_values(array_filter($db['groups']??[],
            static fn($g) => (int)$g['project_id'] === $projectId)), 'id'));
        $eventIds = array_map('intval', array_column(array_values(array_filter($db['community_events']??[],
            static fn($e) => (int)$e['project_id'] === $projectId)), 'id'));
        $versionIds = array_map('intval', array_column(array_values(array_filter($db['versions']??[],
            static fn($v) => (int)$v['project_id'] === $projectId)), 'id'));
        $filter = static function(string $table, callable $keep) use (&$db): void {
            if (isset($db[$table]) && is_array($db[$table])) {
                $db[$table] = array_values(array_filter($db[$table], $keep));
            }
        };
        $filter('projects', static fn($row) => (int)$row['id'] !== $projectId);
        $filter('features', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('groups', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('configs', static fn($row) => !in_array((int)$row['feature_id'], $featureIds, true));
        $filter('requests', static fn($row) => !in_array((int)$row['feature_id'], $featureIds, true));
        $filter('memberships', static fn($row) => !in_array((int)$row['group_id'], $groupIds, true));
        $filter('cohort_memberships', static fn($row) => !in_array((int)$row['group_id'], $groupIds, true));
        $filter('project_memberships', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('versions', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('release_history', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('release_submissions', static fn($row) => (int)($row['project_id']??0) !== $projectId
            && !(($row['type']??'') === 'version' && in_array((int)($row['target_id']??0), $versionIds, true)));
        $filter('decision_logs', static fn($row) => ($row['project'] ?? '') !== $project['key']);
        $filter('project_feature_records', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('community_events', static fn($row) => (int)$row['project_id'] !== $projectId);
        $filter('community_registrations', static fn($row) => !in_array((int)($row['event_id']??0), $eventIds, true));
        $filter('community_activity', static fn($row) => !in_array((int)($row['event_id']??0), $eventIds, true));
        $filter('community_notifications', static fn($row) => !in_array((int)($row['event_id']??0), $eventIds, true));
        foreach (array_keys($db['active_versions'] ?? []) as $key) {
            if (str_starts_with((string)$key, $projectId.':')) unset($db['active_versions'][$key]);
        }
        // Deliberately retain audit log, user accounts, and unrelated projects.
    }
}
