<?php
/**
 * Sidebar FlagFlow - menu phân quyền, độc lập với logic nghiệp vụ.
 * Được require bên trong layout_start() sau khi đăng nhập.
 */
declare(strict_types=1);
$role = (string)(user()['role'] ?? 'tester');
$menu = match ($role) {
    'admin' => [
        ['projects','Quản lý dự án','bi-folder2-open'],
        ['preview','Ứng dụng các dự án','bi-window-stack'],
        ['features','Xem cấu hình','bi-toggles'],
        ['groups','Nhóm thử nghiệm','bi-people'],
        ['versions','Phiên bản & phát hành','bi-layers'],
        ['api_info','API & tích hợp','bi-braces'],
        ['approvals','Phê duyệt yêu cầu','bi-patch-check'],
        ['audit','Nhật ký hoạt động','bi-clock-history'],
        ['accounts','Quản lý tài khoản','bi-person-badge'],
    ],
    'developer' => [
        ['projects','Dự án của tôi','bi-folder2-open'],
        ['preview','Ứng dụng các dự án','bi-window-stack'],
        ['features','Chỉnh sửa tính năng','bi-sliders'],
        ['submit','Gửi tính năng mới','bi-send'],
        ['my_requests','Yêu cầu đã gửi','bi-inboxes'],
        ['groups','Nhóm thử nghiệm','bi-people'],
        ['versions','Phiên bản & bản nháp','bi-layers'],
        ['api_info','API & tích hợp','bi-braces'],
    ],
    default => [
        ['test_queue','Kiểm thử & xác nhận','bi-clipboard-check'],
        ['my_features','Tính năng của tôi','bi-stars'],
        ['preview','Ứng dụng các dự án','bi-window-stack'],
    ],
};
$open = $page !== 'dashboard' && in_array($page, array_column($menu,0), true);
$roleName = ['admin'=>'Quản trị viên','developer'=>'Lập trình viên','tester'=>'Người sử dụng'][$role] ?? 'Thành viên';
$initial = h(mb_substr((string)(user()['name'] ?? 'U'),0,1));
$displayName = h((string)(user()['name'] ?? 'Người dùng'));
$pendingCount = count(array_filter($db['requests'] ?? [], static fn($r) => ($r['status'] ?? '')==='tester_approved'));
$pendingCount += count(array_filter($db['release_submissions'] ?? [], static fn($r) => ($r['status'] ?? '')==='tester_approved'));
$testerPendingCount=count(array_filter($db['requests']??[],static fn($r)=>ReviewWorkflow::normalize($r['status']??'')==='pending_test'))+count(array_filter($db['release_submissions']??[],static fn($r)=>($r['status']??'')==='pending_test'));
?>
<div class="ff-sidebar-inner">
    <a href="/?p=dashboard" class="ff-brand d-flex align-items-center gap-3 text-decoration-none">
        <span class="ff-brand-mark"><i class="bi bi-intersect"></i></span>
        <span><strong>FlagFlow</strong><small>FEATURE MANAGEMENT</small></span>
    </a>
    <div class="ff-workspace-label">KHÔNG GIAN LÀM VIỆC</div>
    <nav class="ff-navigation" aria-label="Menu chức năng">
        <a class="ff-nav-item <?= $page==='dashboard'?'is-active':'' ?>" href="/?p=dashboard">
            <i class="bi bi-grid-1x2"></i><span>Tổng quan</span>
        </a>
        <a class="ff-nav-item ff-search-nav <?= $page==='search'?'is-active':'' ?>" href="/?p=search">
            <i class="bi bi-search"></i><span>Tìm kiếm chức năng</span>
            <i class="bi bi-chevron-right ff-search-nav-arrow"></i>
        </a>
        <details class="ff-project-tree" <?= $open?'open':'' ?>>
            <summary class="ff-nav-item ff-tree-toggle <?= $open?'is-active':'' ?>">
                <i class="bi bi-folder2"></i><span>Dự án</span>
                <i class="bi bi-chevron-down ff-chevron"></i>
            </summary>
            <div class="ff-menu-children">
                <?php foreach ($menu as [$slug,$name,$icon]):
                    $menuLink='/?p='.$slug;
                    $isActive=$page===$slug || ($slug==='preview' && in_array($page,['feature_app','project_feature'],true));
                ?>
                    <a href="<?= h($menuLink) ?>" class="ff-child-link <?= $isActive?'is-active':'' ?>">
                        <i class="bi <?= h($icon) ?>"></i><span><?= h($name) ?></span>
                        <?php if (($slug==='approvals' && $pendingCount>0) || ($slug==='test_queue' && $testerPendingCount>0)): ?>
                            <span class="ff-count"><?= $slug==='test_queue'?$testerPendingCount:$pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
        
    </nav>
    <div class="ff-sidebar-bottom mt-auto">
        <div class="ff-sidebar-help"><i class="bi bi-shield-check"></i>
            <span><?= $role==='admin'?'Quản lý phê duyệt, phát hành và quyền truy cập':($role==='developer'?'Chỉnh sửa và gửi Tester kiểm thử trước khi Admin duyệt':'Kiểm thử dự án nhận từ Dev và sử dụng tính năng đã phát hành') ?></span>
        </div>
        <div class="ff-sidebar-profile d-flex align-items-center gap-3">
            <a href="/?p=profile" class="ff-user-avatar ff-sidebar-avatar-link" title="Trang cá nhân">
                <?php $pic=ProfileService::avatarUrl((string)(user()['avatar']??'')); ?>
                <?php if($pic!==''): ?><img src="<?= h($pic) ?>" class="ff-avatar-img" alt="Ảnh đại diện"><?php else: ?><?= $initial ?><?php endif; ?>
            </a>
            <div class="ff-profile-info"><a href="/?p=profile" class="ff-profile-name-link"><strong><?= $displayName ?></strong></a><small><?= h($roleName) ?></small></div>
            <a class="ff-logout" href="/?p=logout" title="Đăng xuất" aria-label="Đăng xuất"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</div>
