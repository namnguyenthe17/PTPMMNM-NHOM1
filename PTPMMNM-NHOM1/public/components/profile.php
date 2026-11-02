<?php
/** Trang hồ sơ của chính người dùng đang đăng nhập. */
declare(strict_types=1);
$account=ProfileService::findUser($db,(int)user()['id']);
$role=(string)$account['role'];
$roleName=['admin'=>'Quản trị viên','developer'=>'Lập trình viên','tester'=>'Người thử nghiệm'][$role]??'Người dùng';
$avatar=ProfileService::avatarUrl((string)($account['avatar']??''));
$initial=mb_substr((string)$account['name'],0,1);
$activities=array_values(array_filter($db['audit']??[],static fn($row)=>(int)($row['user_id']??0)===(int)$account['id']));
$activities=array_slice(array_reverse($activities),0,8);
$projectCount=$role==='admin' ? count($db['projects']??[]) : count(array_filter($db['projects']??[],fn($p)=>ProjectAccess::canView($db,$account,(int)$p['id'])));
?>
<div class="ff-profile-page">
  <div class="ff-profile-heading">
    <div><div class="ff-profile-eyebrow"><i class="bi bi-house"></i> FlagFlow / Tài khoản / Hồ sơ</div>
      <h1>Trang cá nhân</h1><p>Quản lý thông tin, bảo mật và hoạt động của tài khoản.</p>
    </div>
    <a href="/?p=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Về tổng quan</a>
  </div>
  <div class="ff-profile-cover"><div class="ff-profile-cover-shape"></div><div><span class="ff-profile-overline">FLAGFLOW · MY WORKSPACE</span><h2>Xin chào, <?= h($account['name']) ?></h2><p>Hồ sơ cá nhân và các thiết lập của bạn</p></div></div>
  <div class="ff-profile-identity ff-surface">
    <div class="ff-profile-photo">
      <?php if($avatar!==''): ?><img src="<?= h($avatar) ?>" class="ff-avatar-img" alt="Ảnh đại diện của bạn">
      <?php else: ?><?= h($initial) ?><?php endif; ?>
    </div>
    <div class="ff-profile-person"><h2><?= h($account['name']) ?> <span class="ff-profile-role-chip"><?= h($roleName) ?></span></h2>
      <p><i class="bi bi-at"></i><?= h($account['username']) ?> <span>·</span> <?= h($account['email']??'Chưa cập nhật email') ?></p>
      <div class="ff-profile-meta"><span><i class="bi bi-shield-check"></i> Tài khoản đang hoạt động</span><span><i class="bi bi-folder-check"></i> <?= $projectCount ?> dự án được xem</span></div>
    </div>
    <a href="#avatar" class="btn btn-primary ff-profile-photo-btn"><i class="bi bi-camera me-2"></i>Đổi ảnh đại diện</a>
  </div>
  <div class="ff-profile-tabs" role="navigation" aria-label="Điều hướng hồ sơ">
    <a href="#info"><i class="bi bi-person-vcard"></i> Thông tin cá nhân</a>
    <a href="#avatar"><i class="bi bi-image"></i> Ảnh đại diện</a>
    <a href="#security"><i class="bi bi-lock"></i> Bảo mật</a>
    <a href="#activity"><i class="bi bi-activity"></i> Hoạt động</a>
  </div>
  <div class="ff-profile-content-grid">
    <div class="ff-profile-main-col">
      <section class="ff-surface ff-profile-card" id="info"><div class="ff-profile-section-head"><span class="ff-profile-section-icon"><i class="bi bi-person-lines-fill"></i></span><div><h3>Thông tin cá nhân</h3><p>Cập nhật tên hiển thị và thông tin liên hệ.</p></div></div>
        <form method="post" action="/" class="ff-profile-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile_update"><input type="hidden" name="back" value="profile">
          <div class="row g-3"><div class="col-md-6"><label class="form-label">Họ và tên <span class="text-danger">*</span></label><input class="form-control" name="name" required maxlength="80" minlength="2" autocomplete="name" value="<?= h($account['name']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Tên đăng nhập</label><input class="form-control" value="<?= h($account['username']) ?>" disabled><div class="form-text">Tên đăng nhập không thể đổi tại đây.</div></div>
          <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="254" autocomplete="email" placeholder="ban@example.com" value="<?= h($account['email']??'') ?>"></div>
          <div class="col-12"><label class="form-label">Giới thiệu bản thân</label><textarea class="form-control" name="bio" rows="3" maxlength="300" placeholder="Ví dụ: Phụ trách quản lý phát hành các tính năng."><?= h($account['bio']??'') ?></textarea><div class="form-text">Tối đa 300 ký tự.</div></div>
          </div><div class="ff-profile-form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-floppy me-2"></i>Lưu thay đổi</button></div>
        </form>
      </section>
      <section class="ff-surface ff-profile-card" id="avatar"><div class="ff-profile-section-head"><span class="ff-profile-section-icon"><i class="bi bi-camera"></i></span><div><h3>Ảnh đại diện</h3><p>Ảnh JPG, PNG hoặc WebP, tối đa 2 MB.</p></div></div>
        <div class="ff-photo-edit-row"><div class="ff-profile-photo ff-photo-preview" id="ff-avatar-preview">
          <?php if($avatar!==''): ?><img class="ff-avatar-img" src="<?= h($avatar) ?>" alt="Ảnh hiện tại"><?php else: ?><?= h($initial) ?><?php endif; ?>
          </div><div class="ff-photo-form-area"><form method="post" action="/" enctype="multipart/form-data" class="ff-photo-upload-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="profile_avatar"><input type="hidden" name="back" value="profile">
              <label for="ff-avatar-file" class="form-label">Chọn ảnh từ máy tính</label>
              <input id="ff-avatar-file" type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp" required>
              <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i>Tải ảnh lên</button>
            </form>
            <?php if($avatar!==''): ?><form method="post" action="/" class="mt-2">
              <?= csrf_field() ?><input type="hidden" name="action" value="profile_avatar_delete"><input type="hidden" name="back" value="profile">
              <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Xóa ảnh hiện tại</button></form><?php endif; ?>
            </div></div>
      </section>
      <section class="ff-surface ff-profile-card" id="security"><div class="ff-profile-section-head"><span class="ff-profile-section-icon"><i class="bi bi-shield-lock"></i></span><div><h3>Đổi mật khẩu</h3><p>Để bảo vệ tài khoản, bạn cần nhập mật khẩu hiện tại.</p></div></div>
        <form method="post" action="/" class="ff-profile-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile_password"><input type="hidden" name="back" value="profile">
          <div class="row g-3"><div class="col-12"><label class="form-label">Mật khẩu hiện tại</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div>
          <div class="col-md-6"><label class="form-label">Mật khẩu mới</label><input class="form-control" type="password" name="password" autocomplete="new-password" minlength="10" maxlength="128" required><div class="form-text">Tối thiểu 10 ký tự.</div></div>
          <div class="col-md-6"><label class="form-label">Nhập lại mật khẩu mới</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password" minlength="10" maxlength="128" required></div></div>
          <div class="ff-profile-form-actions"><button type="submit" class="btn btn-primary"><i class="bi bi-key me-2"></i>Đổi mật khẩu</button></div>
        </form>
      </section>
      <section class="ff-surface ff-profile-card" id="activity"><div class="ff-profile-section-head"><span class="ff-profile-section-icon"><i class="bi bi-clock-history"></i></span><div><h3>Hoạt động gần đây</h3><p>Nhật ký thao tác của chính tài khoản bạn.</p></div></div>
        <?php if(!$activities): ?><div class="ff-profile-empty">Chưa có hoạt động nào được ghi lại.</div>
        <?php else: ?><div class="ff-profile-events">
          <?php foreach($activities as $event): ?><div class="ff-profile-event"><span class="ff-profile-event-dot"><i class="bi bi-check2"></i></span><div><strong><?= h($event['action']??'Thao tác') ?></strong><p><?= h($event['detail']??'') ?></p><small><?= h($event['at']??'') ?></small></div></div><?php endforeach; ?>
        </div><?php endif; ?>
      </section>
    </div>
    <aside class="ff-profile-side-col">
      <div class="ff-surface ff-profile-card"><div class="ff-profile-section-head"><span class="ff-profile-section-icon"><i class="bi bi-shield-check"></i></span><div><h3>Vai trò của bạn</h3><p><?= h($roleName) ?></p></div></div>
        <?php if($role==='admin'): ?><p>Được quản lý tài khoản, phê duyệt, phát hành và theo dõi lịch sử hệ thống.</p><div class="ff-profile-quick-links">
          <a href="/?p=approvals"><i class="bi bi-patch-check"></i>Phê duyệt yêu cầu<i class="bi bi-chevron-right"></i></a>
          <a href="/?p=accounts"><i class="bi bi-people"></i>Quản lý tài khoản<i class="bi bi-chevron-right"></i></a>
          <a href="/?p=audit"><i class="bi bi-clock-history"></i>Nhật ký hệ thống<i class="bi bi-chevron-right"></i></a></div>
        <?php elseif($role==='developer'): ?><p>Bạn có thể phát triển và gửi bản cho Tester kiểm thử, theo dõi yêu cầu của mình.</p><div class="ff-profile-quick-links"><a href="/?p=projects"><i class="bi bi-folder"></i>Dự án của tôi<i class="bi bi-chevron-right"></i></a><a href="/?p=my_requests"><i class="bi bi-send"></i>Yêu cầu đã gửi<i class="bi bi-chevron-right"></i></a></div>
        <?php else: ?><p>Bạn được xem và thử các tính năng có quyền truy cập, và kiểm thử bản được Dev gửi.</p><div class="ff-profile-quick-links"><a href="/?p=test_queue"><i class="bi bi-clipboard-check"></i>Kiểm thử & xác nhận<i class="bi bi-chevron-right"></i></a><a href="/?p=community"><i class="bi bi-calendar-event"></i>Sử dụng ứng dụng<i class="bi bi-chevron-right"></i></a></div><?php endif; ?>
      </div>
      <div class="ff-surface ff-profile-card ff-profile-tip"><i class="bi bi-lightbulb"></i><strong>Bảo mật tài khoản</strong><p>Không chia sẻ mật khẩu với thành viên khác. Dùng ảnh cá nhân phù hợp và thay mật khẩu nếu nghi ngờ bị lộ.</p></div>
    </aside>
  </div>
</div>
