<?php
declare(strict_types=1);
$actor=user();$pid=(int)($_GET['project']??0);$fid=(int)($_GET['id']??0);
$env=(string)($_GET['env']??'beta');
if(($actor['role']??'')==='tester')$env='beta';
try{[$p,$f]=ProjectFeatureRuntime::permission($db,$actor,$pid,$fid,$env);}
catch(DomainException $e){http_response_code(403);echo '<div class="alert alert-warning">'.h($e->getMessage()).'</div>';return;}
$slug=ProjectFeatureRuntime::dedicatedSlug($f);
if($slug!==null):
  $_GET['feature']=$slug;
  require __DIR__.'/feature_apps.php';
  return;
endif;
$records=ProjectFeatureRuntime::records($db,$actor,$pid,$fid);
?>
<section class="ff-multi-project">
  <a class="btn btn-outline-secondary mb-3" href="/?p=preview&amp;project=<?=$pid?>&amp;env=<?=h($env)?>"><i class="bi bi-arrow-left"></i> Trở về dự án</a>
  <div class="ff-project-feature-header"><div><div class="ff-kicker"><?=h($p['name'])?> / <?=h(strtoupper($env))?></div><h2><?=h($f['name'])?></h2><p><?=h($f['description']??'')?></p></div><span class="badge text-bg-success">Đã bật cho tài khoản</span></div>
  <div class="alert alert-info"><strong>Không gian dữ liệu dùng chung:</strong> Tính năng này chưa có module PHP nghiệp vụ riêng (như thanh toán, trò chơi, AI...). Bạn có thể nhập, lưu và theo dõi bản ghi thực tế theo từng dự án/tính năng; muốn chạy nghiệp vụ đặc thù, Developer cần viết module riêng rồi gửi Tester kiểm thử và Admin phê duyệt.</div>
  <div class="row g-4">
  <div class="col-lg-5"><div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
    <h3 class="h5 fw-bold mb-3">Tạo bản ghi mới</h3>
    <form method="post" action="/">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="project_feature_record_create">
      <input type="hidden" name="project_id" value="<?=$pid?>">
      <input type="hidden" name="feature_id" value="<?=$fid?>">
      <input type="hidden" name="environment" value="<?=h($env)?>">
      <div class="mb-3"><label class="form-label fw-semibold" for="genericTitle">Tiêu đề</label><input id="genericTitle" class="form-control" name="title" minlength="2" maxlength="120" required placeholder="Nội dung cần thực hiện"></div>
      <div class="mb-3"><label class="form-label fw-semibold" for="genericDescription">Thông tin chi tiết</label><textarea id="genericDescription" class="form-control" name="description" rows="5" maxlength="2000" placeholder="Nhập thông tin cần lưu trong tính năng này"></textarea></div>
      <button type="submit" class="btn btn-primary w-100">Lưu vào dự án <i class="bi bi-check2-circle"></i></button>
    </form>
  </div></div></div>
  <div class="col-lg-7"><div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
    <h3 class="h5 fw-bold">Bản ghi đã lưu <span class="badge bg-primary-subtle text-primary"><?=count($records)?></span></h3>
    <?php if(!$records):?><p class="text-secondary mt-3">Chưa có bản ghi nào. Hãy nhập nội dung ở biểu mẫu bên trái.</p><?php endif; ?>
    <?php foreach($records as $r):?>
      <article class="ff-feature-record"><div class="d-flex justify-content-between gap-3"><div><strong><?=h($r['title'])?></strong><div class="small text-secondary">#<?=(int)$r['id']?> · <?=h($r['created_at'])?></div></div><span class="badge <?=$r['status']==='done'?'text-bg-success':'text-bg-warning'?> align-self-start"><?=$r['status']==='done'?'Hoàn thành':'Đang làm'?></span></div>
        <p class="my-2"><?=nl2br(h($r['description']))?></p>
        <?php if((int)$r['created_by']===(int)$actor['id']):?>
        <form method="post" action="/" class="mt-2">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="project_feature_record_toggle">
          <input type="hidden" name="record_id" value="<?=(int)$r['id']?>">
          <input type="hidden" name="project_id" value="<?=$pid?>"><input type="hidden" name="feature_id" value="<?=$fid?>">
          <input type="hidden" name="status" value="<?=$r['status']==='done'?'open':'done'?>">
          <button class="btn btn-sm btn-outline-primary"><?=$r['status']==='done'?'Đánh dấu đang làm':'Đánh dấu hoàn thành'?></button>
        </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div></div></div>
  </div>
</section>
