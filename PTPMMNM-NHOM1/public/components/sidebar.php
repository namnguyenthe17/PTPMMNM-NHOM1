<?php
/** Điều hướng Enterprise: mọi liên kết dựa trên quyền thực tế. */
declare(strict_types=1);
$navRole = (string)(user()['role'] ?? 'tester');
$navSections = match ($navRole) {
 'admin' => [
   'QUẢN TRỊ HỆ THỐNG' => [
     ['dashboard','Tổng quan','bi-grid-1x2'],['projects','Dự án','bi-folder2-open'],
     ['approvals','Phê duyệt yêu cầu','bi-shield-check'],['versions','Phiên bản & phát hành','bi-layers'],
     ['accounts','Quản lý tài khoản','bi-people'],['groups','Nhóm thử nghiệm','bi-person-check'],
   ],
   'CÔNG CỤ & THEO DÕI' => [
     ['preview','Ứng dụng các dự án','bi-window-stack'],['features','Cấu hình tính năng','bi-toggles'],
     ['search','Tìm kiếm','bi-search'],['api_info','API & tích hợp','bi-braces'],['audit','Nhật ký hệ thống','bi-clock-history']
   ]
 ],
 'developer' => [
   'KHÔNG GIAN PHÁT TRIỂN' => [
     ['dashboard','Tổng quan','bi-grid-1x2'],['projects','Dự án của tôi','bi-folder2-open'],
     ['features','Tính năng của tôi','bi-sliders'],['submit','Gửi tính năng mới','bi-send'],
     ['my_requests','Yêu cầu đã gửi','bi-inboxes'],['versions','Phiên bản & bản nháp','bi-layers']
   ],
   'CÔNG CỤ' => [
     ['preview','Ứng dụng các dự án','bi-window-stack'],['groups','Nhóm thử nghiệm','bi-people'],
     ['search','Tìm kiếm','bi-search'],['api_info','API & tích hợp','bi-braces']
   ]
 ],
 default => [
   'TRUNG TÂM KIỂM THỬ' => [
     ['dashboard','Tổng quan','bi-grid-1x2'],['test_queue','Hàng chờ kiểm thử','bi-clipboard-check'],
     ['my_features','Tính năng của tôi','bi-stars'],['preview','Sử dụng ứng dụng','bi-window-stack']
   ],
   'CÁ NHÂN' => [ ['search','Tìm kiếm','bi-search'],['profile','Trang cá nhân','bi-person-badge'] ]
 ]
};
$navRoleLabel=['admin'=>'Quản trị viên','developer'=>'Lập trình viên','tester'=>'Kiểm thử viên'][$navRole]??'Thành viên';
$navName=(string)(user()['name']??'Người dùng');
$navAvatar=ProfileService::avatarUrl((string)(user()['avatar']??''));
$navPendingAdmin=count(array_filter($db['requests']??[],static fn($r)=>($r['status']??'')==='tester_approved'))+count(array_filter($db['release_submissions']??[],static fn($r)=>($r['status']??'')==='tester_approved'));
$navPendingTest=count(array_filter($db['requests']??[],static fn($r)=>ReviewWorkflow::normalize((string)($r['status']??''))==='pending_test'))+count(array_filter($db['release_submissions']??[],static fn($r)=>($r['status']??'')==='pending_test'));
?>
<div class="ff-sidebar-inner ff-enterprise-sidebar-inner">
  <a href="/?p=dashboard" class="ff-brand ff-enterprise-brand text-decoration-none">
    <span class="ff-brand-mark" aria-hidden="true"><i class="bi bi-intersect"></i></span>
    <span><strong>FlagFlow</strong><small>Feature Management</small></span>
  </a>
  <div class="ff-enterprise-workspace"><span class="ff-enterprise-workspace-icon"><i class="bi bi-layers"></i></span><span><b>Workspace</b><small><?= h($navRoleLabel) ?></small></span><i class="bi bi-chevron-down" aria-hidden="true"></i></div>
  <nav class="ff-navigation ff-enterprise-navigation" aria-label="Điều hướng theo vai trò">
    <?php foreach ($navSections as $section => $links): ?>
      <div class="ff-enterprise-menu-title"><?= h($section) ?></div>
      <?php foreach ($links as [$slug,$label,$icon]):
        $active=$page===$slug||($slug==='preview'&&in_array($page,['feature_app','project_feature'],true));
        $count=$slug==='approvals'?$navPendingAdmin:($slug==='test_queue'?$navPendingTest:0);
      ?>
        <a class="ff-nav-item <?= $active?'is-active':'' ?>" href="/?p=<?= h($slug) ?>" <?= $active?'aria-current="page"':'' ?>><i class="bi <?= h($icon) ?>" aria-hidden="true"></i><span><?= h($label) ?></span><?php if($count>0): ?><span class="ff-count"><?= (int)$count ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="ff-sidebar-bottom mt-auto">
    <div class="ff-enterprise-sidebar-status"><span class="ff-enterprise-status-dot"></span><span><b>FlagFlow Workspace</b><small>Quy trình phát hành nội bộ</small></span></div>
    <div class="ff-sidebar-profile d-flex align-items-center gap-3">
      <a class="ff-user-avatar ff-sidebar-avatar-link" href="/?p=profile" title="Trang cá nhân">
        <?php if($navAvatar): ?><img src="<?= h($navAvatar) ?>" class="ff-avatar-img" alt="Ảnh đại diện"><?php else: ?><?= h(mb_substr($navName,0,1)) ?><?php endif; ?>
      </a>
      <div class="ff-profile-info"><a href="/?p=profile" class="ff-profile-name-link"><strong><?= h($navName) ?></strong></a><small><?= h($navRoleLabel) ?></small></div>
      <a class="ff-logout" href="/?p=logout" aria-label="Đăng xuất" title="Đăng xuất"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</div>
