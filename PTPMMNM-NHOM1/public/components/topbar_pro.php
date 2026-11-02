<?php
/** Thanh công cụ Bootstrap - dữ liệu thông báo thực, có phân quyền. */
declare(strict_types=1);


/** Biểu tượng SVG tự chứa: không cần Bootstrap Icons font hay CDN. */
if (!function_exists('ffProSvg')) {
    function ffProSvg(string $name, string $extraClass = ''): string {
        static $paths = [
            'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
            'search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'plus'=>'<path d="M12 5v14M5 12h14"/>',
            'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
            'moon'=>'<path d="M20.5 14.3A8.7 8.7 0 0 1 9.7 3.5 8.8 8.8 0 1 0 20.5 14.3Z"/><path d="m17 3 .5 1.5L19 5l-1.5.5L17 7l-.5-1.5L15 5l1.5-.5Z"/>',
            'sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
            'chevron-down'=>'<path d="m6 9 6 6 6-6"/>',
            'chevron-right'=>'<path d="m9 6 6 6-6 6"/>',
            'arrow-up-right'=>'<path d="M7 17 17 7M7 7h10v10"/>',
            'arrow-right'=>'<path d="M4 12h16m-7-7 7 7-7 7"/>',
            'check-circle'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'clipboard-check'=>'<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4V2h6v2M8 13l3 3 5-5"/>',
            'patch-check'=>'<path d="m12 2 2.8 1.6 3.2-.1 1.1 3 2.6 2-.9 3.1.9 3.1-2.6 2-1.1 3-3.2-.1L12 22l-2.8-1.6-3.2.1-1.1-3-2.6-2 .9-3.1-.9-3.1 2.6-2 1.1-3 3.2.1Z"/><path d="m8.5 12 2.5 2.5L15.5 10"/>',
            'rocket-takeoff'=>'<path d="M12 15c3-1 6-4 8-11-7 2-10 5-11 8l3 3ZM9 12l-3 1-2 4 5-1m3-1 1 3-2 2-1-5M8 16l-2 2"/><circle cx="15" cy="9" r="1"/>',
            'folder2-open'=>'<path d="M3 7V5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v2H7a2 2 0 0 0-2 2l-2 9M5 20l3-9h13l-3 9H5Z"/>',
            'layers'=>'<path d="m12 2 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5m-18 5 9 5 9-5"/>',
            'sliders'=>'<path d="M4 7h16M4 17h16M8 4v6m8 4v6"/>',
            'send'=>'<path d="m22 2-7 20-4-9-9-4 20-7ZM11 13l11-11"/>',
            'inboxes'=>'<path d="M4 5h16v5h-4l-2 3h-4l-2-3H4V5Zm0 9h4l2 3h4l2-3h4v5H4v-5Z"/>',
            'stars'=>'<path d="m12 3 1.6 5.4L19 10l-5.4 1.6L12 17l-1.6-5.4L5 10l5.4-1.6L12 3Zm7 12 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z"/>',
            'calendar-check'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 10h18m-13 5 2 2 4-4"/>',
            'arrow-counterclockwise'=>'<path d="M3 11a9 9 0 1 1 2.3 6M3 3v8h8"/>',
            'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'shield-lock'=>'<path d="M12 2 4 5v6c0 5 3.5 8.5 8 11 4.5-2.5 8-6 8-11V5l-8-3Z"/><rect x="9" y="11" width="6" height="5" rx="1"/><path d="M10 11V9a2 2 0 0 1 4 0v2"/>',
            'people'=>'<circle cx="9" cy="8" r="3"/><path d="M2 20v-2a6 6 0 0 1 12 0v2H2Zm14-15a3 3 0 0 1 0 6m-1 3a6 6 0 0 1 7 6"/>',
            'clock-history'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
            'logout'=>'<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5m4-5 4-4-4-4m4 4H9"/>',
        ];
        $body = $paths[$name] ?? $paths['stars'];
        $class = 'ff-top-svg'.($extraClass !== '' ? ' '.htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') : '');
        return '<svg class="'.$class.'" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$body.'</svg>';
    }
}

$ffUser = user() ?? [];
$ffUserId = (int)($ffUser['id'] ?? 0);
$ffRole = (string)($ffUser['role'] ?? 'tester');
$ffRoleName = ['admin'=>'Quản trị viên','developer'=>'Lập trình viên','tester'=>'Người thử nghiệm'][$ffRole] ?? 'Thành viên';
$ffUsername = (string)($ffUser['name'] ?? 'Người dùng');
$ffPic = ProfileService::avatarUrl((string)($ffUser['avatar'] ?? ''));
$ffInitial = mb_substr($ffUsername, 0, 1);
$ffNotifications = [];
$ffProjectAccess = static fn(int $pid): bool => ProjectAccess::canView($db, $ffUser, $pid);
$ffFeatureSubject = static function (array $request) use ($db): array {
    $feature = Policy::find($db['features'] ?? [], (int)($request['feature_id'] ?? 0));
    return [(string)($feature['name'] ?? 'Tính năng'), (int)($feature['project_id'] ?? 0)];
};
$ffReleaseSubject = static function (array $r) use ($db): string {
    if (($r['type'] ?? '') === 'project') {
        $p = Policy::find($db['projects'] ?? [], (int)($r['project_id'] ?? 0));
        return 'Dự án '.(string)($p['name'] ?? '#'.($r['project_id'] ?? ''));
    }
    $v = Policy::find($db['versions'] ?? [], (int)($r['target_id'] ?? 0));
    return 'Phiên bản #'.(int)($r['target_id'] ?? 0).' · '.(string)($v['environment'] ?? '');
};

foreach ($db['requests'] ?? [] as $r) {
    [$subject, $pid] = $ffFeatureSubject($r);
    if (!$ffProjectAccess($pid)) continue;
    $status = ReviewWorkflow::normalize((string)($r['status'] ?? ''));
    $owned = (int)($r['requested_by'] ?? 0) === $ffUserId;
    $notice = null;
    if ($ffRole === 'tester' && $status === 'pending_test') {
        $notice = ['title'=>'Cần kiểm thử tính năng', 'detail'=>$subject, 'url'=>'/?p=test_queue', 'icon'=>'clipboard-check', 'kind'=>'amber'];
    } elseif ($ffRole === 'admin' && $status === 'tester_approved') {
        $notice = ['title'=>'Tester đã duyệt · Chờ phát hành', 'detail'=>$subject, 'url'=>'/?p=approvals', 'icon'=>'patch-check', 'kind'=>'violet'];
    } elseif ($ffRole === 'developer' && $owned && in_array($status, ['tester_rejected','rejected','returned_dev'], true)) {
        $notice = ['title'=>'Yêu cầu được trả về để sửa', 'detail'=>$subject, 'url'=>'/?p=my_requests', 'icon'=>'arrow-counterclockwise', 'kind'=>'rose'];
    }
    if ($notice !== null) {
        $notice['when'] = (string)($r['tested_at'] ?? $r['created_at'] ?? '');
        $ffNotifications[] = $notice;
    }
}
foreach ($db['release_submissions'] ?? [] as $r) {
    $pid = (int)($r['project_id'] ?? 0);
    if (!$ffProjectAccess($pid)) continue;
    $status = (string)($r['status'] ?? '');
    $subject = $ffReleaseSubject($r);
    $owned = (int)($r['requested_by'] ?? 0) === $ffUserId;
    $notice = null;
    if ($ffRole === 'tester' && $status === 'pending_test') {
        $notice = ['title'=>'Dự án / phiên bản chờ kiểm thử', 'detail'=>$subject, 'url'=>'/?p=test_queue', 'icon'=>'clipboard-check', 'kind'=>'amber'];
    } elseif ($ffRole === 'admin' && $status === 'tester_approved') {
        $notice = ['title'=>'Bản đã kiểm thử đạt · Chờ Admin', 'detail'=>$subject, 'url'=>'/?p=approvals', 'icon'=>'rocket-takeoff', 'kind'=>'violet'];
    } elseif ($ffRole === 'developer' && $owned && in_array($status, ['tester_rejected','rejected','returned_dev'], true)) {
        $notice = ['title'=>'Bản gửi bị trả lại', 'detail'=>$subject, 'url'=>'/?p=my_requests', 'icon'=>'arrow-counterclockwise', 'kind'=>'rose'];
    }
    if ($notice !== null) {
        $notice['when'] = (string)($r['tested_at'] ?? $r['created_at'] ?? '');
        $ffNotifications[] = $notice;
    }
}
// Thông báo CommunityPass được lưu theo ID tài khoản; chỉ lấy mục chưa đọc.
foreach ($db['community_notifications'] ?? [] as $n) {
    if ((int)($n['user_id'] ?? 0) !== $ffUserId || !empty($n['read_at'])) continue;
    $ffNotifications[] = [
        'title'=>'CommunityPass',
        'detail'=>(string)($n['message'] ?? 'Thông báo mới'),
        'url'=>'/?p=community',
        'icon'=>'calendar-check',
        'kind'=>'mint',
        'when'=>(string)($n['created_at'] ?? '')
    ];
}
usort($ffNotifications, static fn($a,$b) => strcmp($b['when']??'', $a['when']??''));
$ffCount = count($ffNotifications);
$ffTopNotifications = array_slice($ffNotifications, 0, 6);
$ffQuickLinks = match ($ffRole) {
    'admin' => [ ['approvals','Duyệt phát hành','bi-patch-check'], ['projects','Quản lý dự án','bi-folder2-open'], ['versions','Phiên bản','bi-layers'] ],
    'developer' => [ ['submit','Gửi tính năng','bi-send'], ['features','Chỉnh sửa tính năng','bi-sliders'], ['my_requests','Yêu cầu của tôi','bi-inboxes'] ],
    default => [ ['test_queue','Kiểm thử','bi-clipboard-check'], ['my_features','Tính năng của tôi','bi-stars'], ['preview','Ứng dụng các dự án','bi-window-stack'] ],
};
$ffNoticePage = $ffRole === 'admin' ? 'approvals' : ($ffRole === 'developer' ? 'my_requests' : 'test_queue');
// Tìm kiếm toàn cục theo vai trò: chỉ đưa vào danh sách những trang và dự án được phép xem.
$ffSearchPages = [
    ['dashboard','Tổng quan hệ thống','dashboard thống kê trang chủ','layers'],
    ['search','Tìm kiếm tất cả tính năng','tìm kiếm danh sách feature flag','search'],
    ['profile','Trang cá nhân','avatar hồ sơ mật khẩu','user'],
];
$ffSearchRolePages = match ($ffRole) {
    'admin' => [
        ['projects','Quản lý dự án','tạo dự án phân quyền','folder2-open'],
        ['features','Quản lý tính năng','bật tắt cấu hình','sliders'],
        ['groups','Nhóm thử nghiệm','beta tester cohort','people'],
        ['versions','Phiên bản và khôi phục','tạo bản nháp phát hành rollback','layers'],
        ['api_info','API và tích hợp','endpoint api','layers'],
        ['approvals','Duyệt phát hành','phê duyệt tester admin','patch-check'],
        ['accounts','Quản lý tài khoản','người dùng phân quyền','people'],
        ['audit','Nhật ký hệ thống','lịch sử hoạt động','clock-history'],
        ['preview','Web mẫu','ứng dụng mẫu trạng thái','stars'],
    ],
    'developer' => [
        ['projects','Dự án của tôi','dự án thành viên','folder2-open'],
        ['features','Chỉnh sửa tính năng','cấu hình development','sliders'],
        ['submit','Gửi tính năng mới','gửi tester kiểm thử yêu cầu','send'],
        ['my_requests','Yêu cầu đã gửi','trạng thái yêu cầu dev','inboxes'],
        ['groups','Nhóm thử nghiệm','beta cohort','people'],
        ['versions','Phiên bản và bản nháp','cấu hình version','layers'],
        ['api_info','API và tích hợp','endpoint','layers'],
        ['preview','Web mẫu','ứng dụng mẫu','stars'],
    ],
    default => [
        ['test_queue','Kiểm thử và xác nhận','bản tester chờ kết quả','clipboard-check'],
        ['my_features','Tính năng của tôi','được cấp quyền','stars'],
        ['preview','Sử dụng ứng dụng','web mẫu beta','stars'],
    ],
};
$ffSuggestions = [];
foreach (array_merge($ffSearchPages, $ffSearchRolePages) as [$slug,$label,$keywords,$icon]) {
    $ffSuggestions[] = ['label'=>$label,'sub'=>'Chức năng hệ thống','url'=>'/?p='.$slug,'keywords'=>$keywords,'icon'=>$icon,'category'=>'nav'];
}
foreach ($db['projects'] ?? [] as $prj) {
    $pid = (int)($prj['id'] ?? 0);
    if ($pid <= 0 || !ProjectAccess::canView($db, $ffUser, $pid)) continue;
    if ($ffRole === 'tester' && !ReviewWorkflow::projectIsPublished($prj)) continue;
    if ($ffRole !== 'tester') {
        $ffSuggestions[] = ['label'=>(string)$prj['name'],'sub'=>'Dự án','url'=>ReviewWorkflow::projectIsPublished($prj)?'/?p=preview&project='.$pid:'/?p=projects',
            'keywords'=>(string)($prj['key'] ?? '').' '.(string)($prj['description'] ?? ''),
            'icon'=>'folder2-open','category'=>'project'];
    }
    $visibleVersion = null;
    if ($ffRole === 'tester') {
        try { $visibleVersion = VersionService::active($db,$pid,'beta'); }
        catch (Throwable $e) { continue; }
    }
    foreach ($db['features'] ?? [] as $flag) {
        if ((int)($flag['project_id'] ?? 0) !== $pid) continue;
        if ($visibleVersion !== null) {
            $present = false;
            foreach ($visibleVersion['flags'] ?? [] as $rule) {
                if ((int)($rule['feature_id'] ?? 0) === (int)$flag['id']) { $present=true; break; }
            }
            if (!$present) continue;
        }
        $fid = (int)$flag['id'];
        $ffSuggestions[] = [
            'label'=>(string)$flag['name'],'sub'=>'Tính năng · '.(string)$prj['name'],
            'url'=>($ffRole==='tester'
                ? '/?p=project_feature&project='.$pid.'&id='.$fid.'&env=beta'
                : '/?p=features#feature-'.$fid),
            'keywords'=>(string)($flag['key']??'').' '.(string)($flag['description']??'').' '.(string)$prj['name'],
            'icon'=>'stars','category'=>'feature'
        ];
    }
}
?>
<button class="ff-mobile-menu" type="button" aria-label="Mở menu" aria-expanded="false" data-sidebar-toggle><?= ffProSvg('menu') ?></button>
<form class="ff-global-search ff-pro-search ff-search-combobox" method="get" action="/" role="search" data-global-search>
    <input type="hidden" name="p" value="search">
    <?= ffProSvg('search') ?>
    <input type="search" name="q" placeholder="Bấm để xem gợi ý hoặc tìm tên chức năng..."
           aria-label="Tìm chức năng, dự án và tính năng" aria-autocomplete="list"
           aria-haspopup="listbox" aria-expanded="false" aria-controls="ff-search-options"
           autocomplete="off" maxlength="100" value="<?= h((string)($_GET['q'] ?? '')) ?>" data-global-search-input>
    <kbd>Ctrl + K</kbd>
    <div class="ff-global-suggest" data-global-search-menu id="ff-search-options" role="listbox" hidden>
        <div class="ff-global-suggest-head"><span>GỢI Ý DÀNH CHO BẠN</span><small>↑↓ chọn · Enter mở · Esc đóng</small></div>
        <div class="ff-global-suggest-items" data-global-search-items>
            <?php foreach ($ffSuggestions as $entry): ?>
                <a class="ff-global-suggest-item" role="option" aria-selected="false"
                   href="<?= h($entry['url']) ?>" data-global-search-item
                   data-search-category="<?= h($entry['category']) ?>"
                   data-search-haystack="<?= h($entry['label'].' '.$entry['keywords'].' '.$entry['sub']) ?>">
                    <span class="ff-global-suggest-icon"><?= ffProSvg($entry['icon']) ?></span>
                    <span class="ff-global-suggest-text"><strong><?= h($entry['label']) ?></strong><small><?= h($entry['sub']) ?></small></span>
                    <?= ffProSvg('arrow-up-right','ff-global-suggest-arrow') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="ff-global-suggest-empty" data-global-search-empty hidden>Không tìm thấy gợi ý phù hợp.</div>
        <button class="ff-global-suggest-all" type="submit">Xem tất cả kết quả tìm kiếm <?= ffProSvg('arrow-right') ?></button>
    </div>
</form>
<div class="ff-header-right ff-pro-right">
    <span class="ff-live ff-pro-live"><span class="ff-live-dot"></span> Hệ thống nội bộ</span>

    <div class="dropdown ff-pro-drop">
        <button class="ff-icon-action ff-pro-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Thao tác nhanh" title="Thao tác nhanh"><?= ffProSvg('plus') ?></button>
        <div class="dropdown-menu dropdown-menu-end ff-pro-menu ff-pro-quick">
            <div class="ff-pro-menu-title">THAO TÁC NHANH</div>
            <?php foreach ($ffQuickLinks as [$slug, $text, $icon]): ?>
                <a class="dropdown-item" href="/?p=<?= h($slug) ?>"><?= ffProSvg(substr($icon, 3)) ?><span><?= h($text) ?></span><?= ffProSvg('arrow-up-right','ff-pro-tail') ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dropdown ff-pro-drop ff-pro-notification">
        <button class="ff-icon-action ff-pro-icon ff-pro-bell" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mở thông báo<?= $ffCount ? ': '.$ffCount.' mục cần xử lý' : '' ?>" title="Thông báo">
            <?= ffProSvg('bell') ?>
            <?php if ($ffCount): ?><span class="ff-notification-count"><?= $ffCount>99?'99+':$ffCount ?></span><?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end ff-pro-menu ff-pro-notice-menu">
            <div class="ff-pro-notice-header"><div><span class="ff-pro-overline">TRUNG TÂM THÔNG BÁO</span><strong>Thông báo công việc</strong></div><span class="ff-pro-counter"><?= $ffCount ?> mục</span></div>
            <?php if (!$ffTopNotifications): ?>
                <div class="ff-pro-notice-empty"><?= ffProSvg('check-circle') ?><strong>Không có việc đang chờ</strong><span>Các thông báo mới sẽ xuất hiện tại đây.</span></div>
            <?php else: ?>
                <div class="ff-pro-notice-list">
                    <?php foreach ($ffTopNotifications as $n): ?>
                        <a href="<?= h($n['url']) ?>" class="ff-pro-notice-item">
                            <span class="ff-pro-notice-icon ff-kind-<?= h($n['kind']) ?>"><?= ffProSvg((string)$n['icon']) ?></span>
                            <span class="ff-pro-notice-content"><strong><?= h($n['title']) ?></strong><small><?= h($n['detail']) ?></small></span>
                            <?= ffProSvg('chevron-right','ff-pro-notice-arrow') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="ff-pro-all-link" href="/?p=<?= h($ffNoticePage) ?>">Đến trang xử lý công việc <?= ffProSvg('arrow-right') ?></a>
        </div>
    </div>

    <button type="button" class="ff-icon-action ff-pro-icon ff-pro-theme" data-theme-toggle aria-label="Chuyển chế độ tối" aria-pressed="false" title="Chế độ sáng / tối"><?= ffProSvg('moon','ff-theme-moon') ?><?= ffProSvg('sun','ff-theme-sun') ?></button>
    <span class="ff-header-divider ff-pro-divider" aria-hidden="true"></span>
    <div class="dropdown ff-account-dropdown ff-pro-account">
        <button type="button" class="ff-header-user ff-account-trigger ff-pro-account-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mở menu tài khoản">
            <span class="ff-header-avatar">
                <?php if ($ffPic): ?><img src="<?= h($ffPic) ?>" alt="Ảnh đại diện" class="ff-avatar-img"><?php else: ?><?= h($ffInitial) ?><?php endif; ?>
            </span>
            <span class="ff-user-caption"><b><?= h($ffUsername) ?></b><small><?= h($ffRoleName) ?></small></span>
            <?= ffProSvg('chevron-down','ff-account-chevron') ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end ff-account-menu ff-pro-menu ff-pro-account-menu">
            <div class="ff-account-menu-head">
                <span class="ff-header-avatar"><?php if ($ffPic): ?><img src="<?= h($ffPic) ?>" alt="" class="ff-avatar-img"><?php else: ?><?= h($ffInitial) ?><?php endif; ?></span>
                <div><b><?= h($ffUsername) ?></b><small>@<?= h((string)($ffUser['username'] ?? '')) ?> · <?= h($ffRoleName) ?></small></div>
            </div>
            <a class="dropdown-item" href="/?p=profile"><?= ffProSvg('user') ?> Trang cá nhân</a>
            <a class="dropdown-item" href="/?p=profile#security"><?= ffProSvg('shield-lock') ?> Đổi mật khẩu</a>
            <?php if ($ffRole === 'admin'): ?>
                <div class="dropdown-divider"></div><span class="dropdown-header">QUẢN TRỊ</span>
                <a class="dropdown-item" href="/?p=accounts"><?= ffProSvg('people') ?> Quản lý tài khoản</a>
                <a class="dropdown-item" href="/?p=audit"><?= ffProSvg('clock-history') ?> Nhật ký hệ thống</a>
            <?php endif; ?>
            <div class="dropdown-divider"></div>
            <form method="post" action="/" class="ff-pro-logout-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="dropdown-item ff-account-signout" type="submit"><?= ffProSvg('logout') ?> Đăng xuất</button>
            </form>
        </div>
    </div>
</div>
