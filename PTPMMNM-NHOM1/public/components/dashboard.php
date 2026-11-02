<?php
/**
 * Trang tổng quan FlagFlow 2026.
 * Chỉ đọc dữ liệu từ Store/MySqlStore, không cập nhật dữ liệu khi mở trang.
 */
declare(strict_types=1);
$dashboardRole = (string)(user()['role'] ?? 'tester');
$visibleProjects = array_values(array_filter($db['projects'] ?? [],
    static fn(array $p): bool => ProjectAccess::canView($db, user(), (int)$p['id'])
));
$projectIds = array_fill_keys(array_map(static fn($p) => (int)$p['id'], $visibleProjects), true);
$features = array_values(array_filter($db['features'] ?? [],
    static fn($f): bool => isset($projectIds[(int)($f['project_id'] ?? -1)])
));
$featureIds = array_fill_keys(array_map(static fn($f) => (int)$f['id'], $features), true);
$configs = array_values(array_filter($db['configs'] ?? [],
    static fn($c): bool => isset($featureIds[(int)($c['feature_id'] ?? -1)])
));
$enabledCount = count(array_filter($configs, static fn($c) => !empty($c['enabled'])));
$disabledCount = max(0, count($configs) - $enabledCount);
$waitingTester = count(array_filter($db['requests'] ?? [],
    static fn($r) => ReviewWorkflow::normalize((string)($r['status'] ?? '')) === 'pending_test'
)) + count(array_filter($db['release_submissions'] ?? [],
    static fn($r) => ($r['status'] ?? '') === 'pending_test'
));
$waitingAdmin = count(array_filter($db['requests'] ?? [],
    static fn($r) => ($r['status'] ?? '') === 'tester_approved'
)) + count(array_filter($db['release_submissions'] ?? [],
    static fn($r) => ($r['status'] ?? '') === 'tester_approved'
));
$envs = [
    'development' => ['name'=>'Development', 'class'=>'dev', 'color'=>'#3188ff'],
    'beta' => ['name'=>'Beta', 'class'=>'beta', 'color'=>'#8065e9'],
    'production' => ['name'=>'Production', 'class'=>'prod', 'color'=>'#14b890'],
];
$environmentTotals = [];
foreach ($envs as $env => $_metadata) {
    $all = array_values(array_filter($configs, static fn($c) => ($c['environment'] ?? '') === $env));
    $environmentTotals[$env] = ['enabled'=>count(array_filter($all, static fn($c)=>!empty($c['enabled']))), 'total'=>count($all)];
}
$maxBar = max(1, ...array_values(array_map(static fn($v) => $v['total'], $environmentTotals)));
$circleTotal = max(1, $enabledCount + $disabledCount);
$circlePercent = (int)round(100*$enabledCount/$circleTotal);
// Thống kê số bản nháp thuộc dự án mà người dùng có thể xem
$draftCount = count(array_filter($db['versions'] ?? [],
    static fn($v) => ($v['status'] ?? '') === 'draft' && isset($projectIds[(int)($v['project_id']??-1)])
));
$pipelineUrl = $dashboardRole === 'admin' ? '/?p=approvals' : ($dashboardRole === 'developer' ? '/?p=my_requests' : '/?p=test_queue');
$pipelineText = $dashboardRole === 'admin' ? 'Duyệt phát hành' : ($dashboardRole === 'developer' ? 'Xem yêu cầu' : 'Vào kiểm thử');
$recentActivity = array_slice(array_reverse($db['audit'] ?? []), 0, 4);
if (!$recentActivity) {
    $recentActivity = array_map(static fn($h)=>['action'=>($h['action']??'release'), 'detail'=>'Phiên bản '.($h['version_id']??'-'), 'at'=>($h['at']??'')],
        array_slice(array_reverse($db['release_history'] ?? []),0,4));
}
$recentFeatures = array_slice(array_reverse($features),0,6);
$projectNames = [];
foreach ($visibleProjects as $proj) $projectNames[(int)$proj['id']] = (string)$proj['name'];
$featureRows = [];
foreach($recentFeatures as $f) {
    $state=[];
    foreach($configs as $c) if((int)$c['feature_id']===(int)$f['id']) $state[$c['environment']]=$c;
    $chosen=null;
    foreach(['production','beta','development'] as $environment) {
        if(!empty($state[$environment]['enabled'])) {$chosen=$environment;break;}
    }
    if($chosen===null) $chosen='development';
    $featureRows[]=['feature'=>$f,'environment'=>$chosen, 'enabled'=>!empty($state[$chosen]['enabled'])];
}
?>
<div class="ff-dashboard">
    <section class="ff-dashboard-welcome">
        <div>
            <div class="ff-dashboard-kicker"><span class="ff-dashboard-wave">✦</span> WORKSPACE / FLAGFLOW</div>
            <h1><?= $dashboardRole === 'tester' ? 'Không gian kiểm thử' : ($dashboardRole === 'developer' ? 'Bảng điều khiển phát triển' : 'Tổng quan hệ thống') ?></h1>
            <p><?= $dashboardRole === 'tester' ? 'Tiếp nhận bản thử nghiệm, xác nhận chất lượng và trải nghiệm tính năng.' : ($dashboardRole === 'developer' ? 'Theo dõi tiến độ phát triển, cấu hình tính năng và các yêu cầu đã gửi.' : 'Theo dõi dự án, tính năng, kiểm thử và phát hành trên một màn hình.') ?></p>
        </div>
        <div class="ff-dashboard-actions">
            <a class="btn btn-outline-primary" href="/?p=projects"><i class="bi bi-folder2-open"></i> Xem dự án</a>
            <a class="btn btn-primary" href="<?= h($pipelineUrl) ?>"><i class="bi bi-arrow-up-right"></i> <?= h($pipelineText) ?></a>
        </div>
    </section>

    <div class="ff-kpi-grid">
        <a class="ff-kpi-card ff-kpi-blue" href="/?p=projects">
            <div class="ff-kpi-top"><span class="ff-kpi-icon"><i class="bi bi-folder2-open"></i></span><span class="ff-kpi-plain">DỰ ÁN</span></div>
            <span class="ff-kpi-label">Tổng dự án</span><strong><?= count($visibleProjects) ?></strong>
            <small><i class="bi bi-check-circle"></i> Dự án được phép truy cập</small>
        </a>
        <a class="ff-kpi-card ff-kpi-purple" href="/?p=search">
            <div class="ff-kpi-top"><span class="ff-kpi-icon"><i class="bi bi-grid"></i></span><span class="ff-kpi-plain">FEATURE FLAGS</span></div>
            <span class="ff-kpi-label">Tổng tính năng</span><strong><?= count($features) ?></strong>
            <small><i class="bi bi-lightning-charge"></i> <?= $enabledCount ?> cấu hình đang bật</small>
        </a>
        <a class="ff-kpi-card ff-kpi-orange" href="<?= h($pipelineUrl) ?>">
            <div class="ff-kpi-top"><span class="ff-kpi-icon"><i class="bi bi-clipboard2-check"></i></span><span class="ff-kpi-plain">TESTING</span></div>
            <span class="ff-kpi-label">Chờ Tester kiểm thử</span><strong><?= $waitingTester ?></strong>
            <small><i class="bi bi-hourglass-split"></i> Bản đang đợi kết quả</small>
        </a>
        <a class="ff-kpi-card ff-kpi-green" href="<?= h($pipelineUrl) ?>">
            <div class="ff-kpi-top"><span class="ff-kpi-icon"><i class="bi bi-rocket-takeoff"></i></span><span class="ff-kpi-plain">RELEASE</span></div>
            <span class="ff-kpi-label">Chờ Admin phát hành</span><strong><?= $waitingAdmin ?></strong>
            <small><i class="bi bi-patch-check"></i> Đã được Tester xác nhận</small>
        </a>
    </div>

    <div class="ff-insight-grid">
        <section class="ff-surface ff-chart-panel">
            <div class="ff-card-title-row"><div><h2>Trạng thái tính năng theo môi trường</h2><p>Số cấu hình đang bật và đang tắt ở mỗi môi trường</p></div>
            <div class="ff-chart-legend"><span><i class="ff-dot" style="background:#3188ff"></i> Development</span><span><i class="ff-dot" style="background:#8065e9"></i> Beta</span><span><i class="ff-dot" style="background:#14b890"></i> Production</span></div></div>
            <div class="ff-bar-chart" role="img" aria-label="Biểu đồ tính năng bật theo môi trường">
                <div class="ff-bar-axis"><span><?= $maxBar ?></span><span><?= (int)ceil($maxBar/2) ?></span><span>0</span></div>
                <div class="ff-bars">
                <?php foreach($environmentTotals as $env=>$counts): $percent=round($counts['enabled']/$maxBar*100); $totalPercent=round($counts['total']/$maxBar*100); ?>
                    <div class="ff-bar-group">
                        <div class="ff-bar-stack" title="<?= h($envs[$env]['name']) ?>: <?= $counts['enabled'] ?> đang bật / <?= $counts['total'] ?> tổng cấu hình">
                            <div class="ff-bar-background" style="height:<?= $totalPercent ?>%"></div>
                            <div class="ff-bar-value ff-<?= $envs[$env]['class'] ?>" style="height:<?= $percent ?>%"><span><?= $counts['enabled'] ?></span></div>
                        </div>
                        <span class="ff-bar-label"><?= h($envs[$env]['name']) ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="ff-chart-note"><i class="bi bi-info-circle"></i> Biểu đồ dùng dữ liệu cấu hình hiện tại, không phải số liệu ước tính.</div>
        </section>
        <section class="ff-surface ff-donut-panel">
            <div class="ff-card-title-row"><div><h2>Tỷ lệ trạng thái cấu hình</h2><p>Phân bố bật / tắt hiện tại</p></div></div>
            <div class="ff-donut-layout">
                <div class="ff-donut" role="img" aria-label="<?= $circlePercent ?> phần trăm cấu hình đang bật" style="--ff-donut-percentage:<?= $circlePercent ?>%"><div><strong><?= $circlePercent ?>%</strong><small>Đang bật</small></div></div>
                <div class="ff-donut-legend">
                    <div><span><i class="ff-dot" style="background:#12b886"></i> Đang bật</span><strong><?= $enabledCount ?></strong></div>
                    <div><span><i class="ff-dot" style="background:#cbd5e5"></i> Đang tắt</span><strong><?= $disabledCount ?></strong></div>
                    <div><span><i class="ff-dot" style="background:#8b6cf0"></i> Bản nháp</span><strong><?= $draftCount ?></strong></div>
                    <div><span><i class="ff-dot" style="background:#ffa63d"></i> Đợi kiểm thử</span><strong><?= $waitingTester ?></strong></div>
                </div>
            </div>
        </section>
        <section class="ff-surface ff-notification-panel">
            <div class="ff-card-title-row"><div><h2>Trạng thái xử lý</h2><p>Công việc cần quan tâm</p></div><a href="<?= h($pipelineUrl) ?>">Xem tất cả <i class="bi bi-arrow-right"></i></a></div>
            <div class="ff-quick-notice-list">
                <a href="<?= h($pipelineUrl) ?>"><span class="ff-notice-icon amber"><i class="bi bi-hourglass-split"></i></span><span><b><?= $waitingTester ?> bản chờ Tester</b><small>Chưa xác nhận kết quả kiểm thử</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="<?= h($pipelineUrl) ?>"><span class="ff-notice-icon green"><i class="bi bi-check2-circle"></i></span><span><b><?= $waitingAdmin ?> bản chờ Admin</b><small>Đã được Tester xác nhận đạt</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="/?p=versions"><span class="ff-notice-icon purple"><i class="bi bi-layers"></i></span><span><b><?= $draftCount ?> bản nháp cấu hình</b><small>Chưa được phát hành</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </section>
    </div>

    <div class="ff-dashboard-midgrid">
        <section class="ff-surface ff-workflow-panel">
            <div class="ff-card-title-row"><div><h2>Luồng quy trình phát hành</h2><p>Mỗi bản thay đổi phải đi qua kiểm thử và phê duyệt</p></div><span class="ff-soft-tag">4 GIAI ĐOẠN</span></div>
            <div class="ff-release-steps">
                <div class="ff-release-step"><span class="ff-step-icon blue"><i class="bi bi-code-slash"></i></span><strong>01 · Developer</strong><small>Phát triển, cấu hình<br>và gửi Tester</small></div>
                <span class="ff-step-arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="ff-release-step"><span class="ff-step-icon green"><i class="bi bi-clipboard-check"></i></span><strong>02 · Tester</strong><small>Kiểm thử và xác nhận<br>Đạt / Không đạt</small></div>
                <span class="ff-step-arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="ff-release-step"><span class="ff-step-icon purple"><i class="bi bi-person-check"></i></span><strong>03 · Admin</strong><small>Phê duyệt và phát hành<br>cấu hình đã đạt</small></div>
                <span class="ff-step-arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="ff-release-step"><span class="ff-step-icon orange"><i class="bi bi-globe2"></i></span><strong>04 · Web mẫu</strong><small>Người dùng trải nghiệm<br>tính năng đã phát hành</small></div>
            </div>
        </section>
        <section class="ff-surface ff-featured-panel">
            <div class="ff-card-title-row"><div><h2>Dự án nổi bật</h2><p>Các dự án bạn có quyền xem</p></div><a href="/?p=projects">Xem tất cả <i class="bi bi-arrow-right"></i></a></div>
            <?php if($visibleProjects): foreach(array_slice($visibleProjects,0,4) as $i=>$p): $projectFeatureCount=count(array_filter($features,static fn($f)=>(int)$f['project_id']===(int)$p['id'])); ?>
                <a class="ff-featured-project" href="/?p=projects"><span class="ff-project-emoji"><i class="bi <?= ['bi-calendar-event','bi-mortarboard','bi-bag','bi-grid-1x2'][$i%4] ?>"></i></span><span><strong><?= h($p['name']) ?></strong><small><?= h(mb_substr((string)($p['description']??'Dự án'),0,65)) ?></small></span><em><?= $projectFeatureCount ?> tính năng</em><i class="bi bi-chevron-right"></i></a>
            <?php endforeach; else: ?><div class="ff-simple-empty">Chưa có dự án được cấp quyền xem.</div><?php endif; ?>
        </section>
    </div>

    <div class="ff-dashboard-bottomgrid">
        <section class="ff-surface ff-recent-table-panel">
            <div class="ff-card-title-row"><div><h2>Tính năng gần đây</h2><p>Trạng thái cấu hình đang lưu trong hệ thống</p></div><a href="/?p=search">Xem tất cả <i class="bi bi-arrow-right"></i></a></div>
            <div class="table-responsive"><table class="ff-overview-table"><thead><tr><th>TÊN TÍNH NĂNG</th><th>DỰ ÁN</th><th>MÔI TRƯỜNG</th><th>TRẠNG THÁI</th><th></th></tr></thead><tbody>
                <?php foreach($featureRows as $item): $f=$item['feature']; $name=$projectNames[(int)$f['project_id']]??'—'; $e=$item['environment']; ?>
                    <tr><td><strong><?= h($f['name']) ?></strong><small><?= h($f['key']) ?></small></td><td><?= h($name) ?></td><td><span class="ff-env-badge ff-env-<?= h($e) ?>"><?= h($envs[$e]['name']) ?></span></td><td><span class="ff-status-text <?= $item['enabled']?'is-on':'is-off' ?>"><i class="bi bi-circle-fill"></i> <?= $item['enabled']?'Đang bật':'Đang tắt' ?></span></td><td><a href="/?p=search&amp;q=<?= rawurlencode((string)$f['name']) ?>" aria-label="Xem tính năng"><i class="bi bi-arrow-up-right"></i></a></td></tr>
                <?php endforeach; if(!$featureRows): ?><tr><td colspan="5">Chưa có tính năng để hiển thị.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
        <section class="ff-surface ff-activity-panel">
            <div class="ff-card-title-row"><div><h2>Hoạt động gần đây</h2><p>Nhật ký thay đổi và phát hành</p></div><?php if($dashboardRole==='admin'):?><a href="/?p=audit">Xem tất cả <i class="bi bi-arrow-right"></i></a><?php endif; ?></div>
            <?php if($recentActivity): ?><div class="ff-timeline">
                <?php foreach($recentActivity as $i=>$a): ?>
                  <div class="ff-timeline-row"><span class="ff-timeline-dot ff-timeline-<?= $i%4 ?>"></span><span><strong><?= h((string)($a['action']??'Thay đổi')) ?></strong><small><?= h(mb_substr((string)($a['detail']??''),0,90)) ?></small></span><time><?= h(str_replace('T',' ',substr((string)($a['at']??''),0,16))) ?></time></div>
                <?php endforeach; ?>
            </div><?php else: ?><div class="ff-simple-empty"><i class="bi bi-clock-history"></i> Chưa có hoạt động nào được ghi nhận.</div><?php endif; ?>
        </section>
    </div>
</div>
