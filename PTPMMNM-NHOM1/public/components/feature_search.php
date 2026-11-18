<?php
/** Tìm kiếm feature flags trong các dự án người dùng được phép xem. */
declare(strict_types=1);
$searchRole = (string)(user()['role'] ?? 'tester');
$featuresForSearch = [];
foreach ($db['projects'] ?? [] as $project) {
    if (!ProjectAccess::canView($db, user(), (int)$project['id'])) continue;
    if($searchRole==='tester' && !ReviewWorkflow::projectIsPublished($project))continue;
    $released=$searchRole==='tester'?VersionService::active($db,(int)$project['id'],'beta'):null;
    foreach ($db['features'] ?? [] as $feature) {
        if ((int)$feature['project_id'] !== (int)$project['id']) continue;
        if($released && !array_filter($released['flags']??[],fn($rule)=>(int)$rule['feature_id']===(int)$feature['id']))continue;
        $betaAvailable = CommunityService::enabled($db, (int)$project['id'], 'beta', (string)$feature['key'], user());
        $featureHref = ($searchRole === 'tester'
            ? '/?p=project_feature&amp;project='.(int)$project['id'].'&amp;id='.(int)$feature['id'].'&amp;env=beta'
            : '/?p=features').($searchRole==='tester'?'':'#feature-'.(int)$feature['id']);
        $featuresForSearch[] = [
            'feature'=>$feature,
            'project'=>$project,
            'href'=>$featureHref,
            'active'=>$betaAvailable,
        ];
    }
}
?>
<section class="ff-search-page" data-feature-catalog>
  <div class="ff-search-hero">
    <div class="ff-search-hero-content">
      <span class="ff-search-hero-kicker"><i class="bi bi-stars"></i> FEATURE DISCOVERY</span>
      <h2>Tìm tính năng bạn cần</h2>
      <p>Chọn tên tính năng để đến đúng vị trí trong ứng dụng. Danh sách được lấy từ dự án bạn có quyền truy cập.</p>
      <div class="ff-search-count"><i class="bi bi-layers"></i> <?= count($featuresForSearch) ?> tính năng</div>
    </div>
    <div class="ff-search-hero-art"><i class="bi bi-search"></i></div>
  </div>
  <div class="ff-search-catalog-panel">
    <label class="ff-search-catalog-label" for="featureCatalogSearch">TÌM THEO TÊN, MÃ HOẶC MÔ TẢ</label>
    <div class="ff-search-catalog-input-wrap">
      <i class="bi bi-search"></i>
      <input id="featureCatalogSearch" type="search" data-feature-catalog-input autocomplete="off" value="<?= h(mb_substr((string)($_GET['q'] ?? ''),0,100)) ?>"
        placeholder="Bấm để xem danh sách hoặc nhập tên tính năng..." aria-describedby="searchHelp">
      <span class="ff-search-catalog-hint"><i class="bi bi-arrow-return-left"></i> Chọn để mở</span>
    </div>
    <p id="searchHelp" class="ff-search-subhelp">Hiển thị <strong data-feature-catalog-count><?= count($featuresForSearch) ?></strong> kết quả · Nhấn vào tính năng để mở.</p>
    <div class="ff-search-results" data-feature-catalog-results>
      <?php foreach ($featuresForSearch as $entry):
        $feature=$entry['feature']; $project=$entry['project']; ?>
        <a class="ff-search-result" data-feature-catalog-item
           data-search-text="<?= h($feature['name'].' '.$feature['key'].' '.($feature['description']??'').' '.$project['name']) ?>"
           href="<?= $entry['href'] ?>">
          <span class="ff-search-result-icon"><i class="bi bi-lightning-charge"></i></span>
          <span class="ff-search-result-body">
            <strong><?= h($feature['name']) ?></strong>
            <small><?= h($project['name']) ?> · <?= h($feature['key']) ?></small>
            <span><?= h($feature['description']??'Tính năng của ứng dụng') ?></span>
          </span>
          <?php if ($searchRole==='tester'): ?>
            <span class="ff-search-result-status <?= $entry['active']?'is-available':'is-locked' ?>">
              <?= $entry['active']?'Được sử dụng':'Chưa được cấp' ?>
            </span>
          <?php else: ?>
            <span class="ff-search-result-status is-available">Xem cấu hình</span>
          <?php endif; ?>
          <i class="bi bi-arrow-up-right ff-search-open"></i>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="ff-search-empty" data-feature-catalog-empty hidden>
      <i class="bi bi-search-heart"></i>
      <h3>Không tìm thấy tính năng</h3>
      <p>Hãy thử một từ khóa khác.</p>
    </div>
  </div>
</section>
