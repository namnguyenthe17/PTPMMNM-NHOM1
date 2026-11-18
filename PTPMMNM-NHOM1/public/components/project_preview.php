<?php
declare(strict_types=1);
$actor=user();
$env=(string)($_GET['env']??'beta');
if (!in_array($env, Policy::ENVS, true) || ($actor['role']??'')==='tester') $env='beta';
$visibleProjects=array_values(array_filter($db['projects']??[],static fn($p)=>ProjectAccess::canView($db,$actor,(int)$p['id']) && ReviewWorkflow::projectIsPublished($p)));
$projectId=(int)($_GET['project']??($visibleProjects[0]['id']??0));
$project=null;
foreach($visibleProjects as $p){ if((int)$p['id']===$projectId){$project=$p;break;} }
?>
<section class="ff-multi-project">
  <div class="ff-project-intro">
    <div><div class="ff-kicker">KHÔNG GIAN ỨNG DỤNG</div><h2>Ứng dụng của các dự án</h2>
      <p>Chọn dự án đã được phát hành rồi mở tính năng bạn được cấp. Mỗi dự án có không gian độc lập.</p></div>
    <span class="badge bg-primary-subtle text-primary rounded-pill"> <?=count($visibleProjects)?> dự án khả dụng</span>
  </div>
  <?php if (!$visibleProjects): ?>
    <div class="alert alert-info">Bạn chưa có dự án đã phát hành và được cấp quyền. Developer gửi Tester, sau khi kiểm thử đạt Admin phát hành thì dự án mới xuất hiện ở đây.</div>
  <?php else: ?>
    <div class="ff-project-pick-grid">
      <?php foreach($visibleProjects as $p): $pid=(int)$p['id']; ?>
      <a href="/?p=preview&amp;project=<?=$pid?>&amp;env=<?=h($env)?>" class="ff-project-pick <?=$project&&$pid===(int)$project['id']?'is-selected':''?>">
        <span class="ff-pick-icon"><i class="bi bi-folder2-open"></i></span>
        <strong><?=h($p['name'])?></strong>
        <small><?=h($p['description']??'Dự án đang hoạt động')?></small>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if($project): ?>
      <form class="d-flex align-items-center gap-3 my-3" method="get">
        <input type="hidden" name="p" value="preview"><input type="hidden" name="project" value="<?= (int)$project['id'] ?>">
        <label class="fw-semibold" for="projectEnvironment">Môi trường:</label>
        <select class="form-select" style="max-width:200px" id="projectEnvironment" name="env" aria-label="Môi trường">
          <?php foreach(Policy::ENVS as $e):if(($actor['role']??'')==='tester' && $e!=='beta')continue; ?>
          <option value="<?=h($e)?>" <?=$e===$env?'selected':''?>><?=h(ucfirst($e))?></option>
          <?php endforeach; ?>
        </select><button class="btn btn-primary">Xem</button>
      </form>
      <div class="ff-project-feature-header"><div><div class="ff-kicker">DỰ ÁN ĐÃ PHÁT HÀNH</div><h3><?=h($project['name'])?></h3><p><?=h($project['description']??'')?></p></div><span class="badge text-bg-light border"><?=h(strtoupper($env))?></span></div>
      <?php if (($actor['role']??'') === 'admin'): ?>
        <div class="ff-preview-admin-guide">
          <div><strong>Quản trị dự án:</strong> Bạn có quyền Manager. Trạng thái sử dụng bên dưới phụ thuộc vào bản phát hành <?=h(ucfirst($env))?> và quy tắc bật/tắt của từng tính năng.</div>
          <div class="ff-preview-guide-actions"><a href="/?p=projects#phan-quyen-du-an" class="btn btn-outline-primary btn-sm">Cấp quyền thành viên</a><a href="/?p=approvals" class="btn btn-primary btn-sm">Phê duyệt phát hành</a><a href="/?p=versions" class="btn btn-outline-secondary btn-sm">Xem phiên bản</a></div>
        </div>
      <?php endif; ?>
      <div class="row g-3">
      <?php
      $hasFeatures=false;
      try{$version=VersionService::active($db,(int)$project['id'],$env);}catch(Throwable){$version=null;}
      $releasedFlags=[];
      foreach($version['flags']??[] as $rule)$releasedFlags[(int)$rule['feature_id']]=true;
      foreach($db['features']??[] as $feature):
        if((int)$feature['project_id']!==(int)$project['id'] || !isset($releasedFlags[(int)$feature['id']]))continue;
        $hasFeatures=true;
        $fid=(int)$feature['id'];
        $decision=$version ? FlagEngine::evaluate($db,$version,$fid,CommunityService::userCode($actor)) : ['enabled'=>false,'reason'=>'no-release'];
        $active=($decision['enabled']??false)===true;
        $denyExplanation = match($decision['reason']??'no-release') {
          'disabled'=>'Tính năng đang TẮT trong phiên bản '.ucfirst($env).' hiện tại. Muốn bật phải gửi Tester kiểm thử và Admin phát hành.',
          'rollout-0'=>'Tỷ lệ triển khai đang đặt 0% cho môi trường này.',
          'deny-list'=>'Tài khoản này nằm trong danh sách chặn sử dụng.',
          'outside-cohort'=>'Tài khoản chưa thuộc nhóm thử nghiệm được chỉ định.',
          'percentage'=>'Tài khoản không nằm trong tỷ lệ người dùng được cấp.',
          'invalid-user-code'=>'Mã người dùng không hợp lệ.',
          default=>'Tính năng chưa có cấu hình phát hành phù hợp với tài khoản này.'
        };
      ?>
        <div class="col-md-6 col-xl-4" id="feature-<?=$fid?>"><article class="ff-project-feature-card h-100">
          <span class="ff-pick-icon"><i class="bi bi-lightning-charge"></i></span>
          <h4><?=h($feature['name'])?></h4><p><?=h($feature['description']??'Tính năng trong dự án')?></p>
          <span class="badge <?=$active?'text-bg-success':'text-bg-secondary'?> mb-3"><?=$active?'Được sử dụng':'Chưa khả dụng'?></span>
          <?php if($active):?>
            <a class="btn btn-primary d-block" href="/?p=project_feature&amp;project=<?=(int)$project['id']?>&amp;id=<?=$fid?>&amp;env=<?=h($env)?>">Mở tính năng <i class="bi bi-arrow-up-right"></i></a>
          <?php else:?>
            <div class="small text-secondary ff-denial-reason"><?=h($denyExplanation)?></div>
            <?php if (($actor['role']??'') === 'admin'): ?><a class="ff-manage-link" href="/?p=versions">Xem bản phát hành &amp; quy tắc →</a><?php endif; ?>
          <?php endif; ?>
        </article></div>
      <?php endforeach; ?>
      <?php if(!$hasFeatures):?><div class="col-12"><div class="alert alert-light border">Dự án chưa có tính năng nào trong phiên bản <?=$env?> được phát hành.</div></div><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
