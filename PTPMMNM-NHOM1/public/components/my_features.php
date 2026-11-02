<?php
/** Trang chỉ sử dụng chức năng cho Tester - không có biểu mẫu chỉnh sửa. */
declare(strict_types=1);
$myId = (int)(user()['id'] ?? 0);
$visibleProjects = array_values(array_filter($db['projects'] ?? [], static fn($p) => ProjectAccess::canView($db,user(),(int)$p['id']) && ReviewWorkflow::projectIsPublished($p)));
$total = 0;
$available = 0;
foreach ($visibleProjects as $p) {
    foreach ($db['features'] ?? [] as $feature) {
        if ((int)$feature['project_id'] !== (int)$p['id']) continue;
        $released=VersionService::active($db,(int)$p['id'],'beta');if(!array_filter($released['flags']??[],fn($rule)=>(int)$rule['feature_id']===(int)$feature['id']))continue;
        $total++;
        if (CommunityService::enabled($db,(int)$p['id'],'beta',(string)$feature['key'],user())) $available++;
    }
}
?>
<div class="ff-view-intro d-flex justify-content-between align-items-start flex-wrap gap-3">
  <div><div class="ff-kicker">TRẢI NGHIỆM NGƯỜI DÙNG</div><h2>Tính năng của tôi</h2>
  <p>Xem và sử dụng các tính năng đã được cấp cho tài khoản của bạn. Bạn không thể thay đổi cấu hình.</p></div>
  <a class="btn primary" href="/?p=preview"><i class="bi bi-box-arrow-up-right"></i> Mở ứng dụng</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="ff-kpi"><span>Dự án được tham gia</span><strong><?= count($visibleProjects) ?></strong><i class="bi bi-folder"></i></div></div>
  <div class="col-md-4"><div class="ff-kpi"><span>Tổng số tính năng</span><strong><?= $total ?></strong><i class="bi bi-layers"></i></div></div>
  <div class="col-md-4"><div class="ff-kpi"><span>Được sử dụng ở Beta</span><strong><?= $available ?></strong><i class="bi bi-check-circle"></i></div></div>
</div>
<?php if (!$visibleProjects): ?>
  <section class="panel"><div class="empty">Tài khoản của bạn chưa được cấp quyền xem dự án. Vui lòng liên hệ quản trị viên.</div></section>
<?php endif; ?>
<?php foreach ($visibleProjects as $project): ?>
  <section class="ff-feature-section panel">
    <div class="section-head"><div><div class="ff-kicker">DỰ ÁN</div><h3><?= h($project['name']) ?></h3>
    <p><?= h($project['description'] ?? 'Danh sách tính năng được công bố') ?></p></div>
    <a class="btn secondary small" href="/?p=preview&amp;project=<?= (int)$project['id'] ?>&amp;env=beta">Trải nghiệm <i class="bi bi-arrow-up-right"></i></a></div>
    <div class="row g-3">
      <?php foreach ($db['features'] ?? [] as $feature): ?>
        <?php if ((int)$feature['project_id'] !== (int)$project['id']) continue;
        $released=VersionService::active($db,(int)$project['id'],'beta');if(!array_filter($released['flags']??[],fn($rule)=>(int)$rule['feature_id']===(int)$feature['id']))continue; ?>
        <?php $on = CommunityService::enabled($db,(int)$project['id'],'beta',(string)$feature['key'],user()); ?>
        <div class="col-12 col-lg-6"><div class="ff-user-feature h-100">
          <div class="d-flex justify-content-between gap-2 align-items-start">
            <span class="ff-mini-icon"><i class="bi bi-lightning-charge"></i></span>
            <span class="pill <?= $on?'pill-green':'pill-neutral' ?>"><?= $on?'Đang khả dụng':'Chưa được cấp' ?></span>
          </div>
          <h4><?= h($feature['name']) ?></h4><p><?= h($feature['description'] ?? 'Tính năng của ứng dụng') ?></p>
          <div class="ff-feature-footer"><code><?= h($feature['key']) ?></code>
            <?php if ($on): ?><a href="/?p=project_feature&amp;project=<?= (int)$project['id'] ?>&amp;id=<?= (int)$feature['id'] ?>&amp;env=beta">Sử dụng <i class="bi bi-arrow-right"></i></a><?php endif; ?>
          </div>
        </div></div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
