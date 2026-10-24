<?php
declare(strict_types=1);
require __DIR__.'/../app/DemoClient.php';
function html(string $s): string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

$project='communitypass';
$env=(string)($_GET['env']??'beta');if(!in_array($env,['development','beta','production'],true))$env='beta';
$feature=(string)($_GET['feature']??'new_registration');if(!preg_match('/^[a-z][a-z0-9_]{2,39}$/D',$feature))$feature='new_registration';
$user=(string)($_GET['user']??'user0001');if(!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$user))$user='user0001';

$simulateError = isset($_GET['simulate_error']) && $_GET['simulate_error'] === '1';
$defaultApi = (string)(getenv('FLAGFLOW_API_URL') ?: 'http://127.0.0.1:8104/api/evaluate');
$api = $simulateError ? 'http://127.0.0.1:9999/api/evaluate' : $defaultApi;

$query=http_build_query(['project'=>$project,'feature'=>$feature,'env'=>$env,'user'=>$user]);
$keyFile=__DIR__.'/../storage/api-key.txt';
$token=is_file($keyFile)?trim((string)file_get_contents($keyFile)):'';

$lastRawResponse = null;
$decision=DemoClient::decide(function()use($api,$query,$token,&$lastRawResponse){
    if($token==='')throw new RuntimeException('Thiếu khóa API key trong storage/api-key.txt');
    $context=stream_context_create(['http'=>['method'=>'GET','timeout'=>2.0,'ignore_errors'=>true,'header'=>"X-API-Key: ".$token."\r\nAccept: application/json\r\n"]]);
    $response=@file_get_contents($api.'?'.$query,false,$context);
    if($response===false)throw new RuntimeException('Lỗi kết nối mạng hoặc không tìm thấy máy chủ API tại '.$api);
    $lastRawResponse = $response;
    return $response;
},function(string $message):void{
    $path=__DIR__.'/../storage/client-errors.log';
    file_put_contents($path,date('c').' '.$message."\n",FILE_APPEND|LOCK_EX);
});

$logFile=__DIR__.'/../storage/client-errors.log';
$recentLogs=[];
if(is_file($logFile)){
    $lines=file($logFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $recentLogs=array_slice(array_reverse($lines),0,5);
}
?><!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CommunityPass · Ứng dụng độc lập gọi API</title>
  <style>
    :root {
      --bg: #f4f6fa;
      --card: #ffffff;
      --text: #1e293b;
      --primary: #4f46e5;
      --primary-hover: #4338ca;
      --border: #e2e8f0;
      --green: #10b981;
      --green-bg: #ecfdf5;
      --red: #ef4444;
      --red-bg: #fef2f2;
      --orange: #f59e0b;
      --orange-bg: #fffbeb;
    }
    body {
      font-family: Inter, system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      margin: 0;
      padding: 30px 16px;
      line-height: 1.5;
    }
    .wrap {
      max-width: 860px;
      margin: 0 auto;
    }
    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 700;
    }
    .badge-blue { background: #e0e7ff; color: #4338ca; }
    .badge-green { background: #d1fae5; color: #065f46; }
    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-orange { background: #fef3c7; color: #92400e; }
    .card {
      background: var(--card);
      border-radius: 16px;
      border: 1px solid var(--border);
      padding: 28px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.04);
      margin-bottom: 24px;
    }
    h1 { margin: 0 0 6px; font-size: 26px; }
    h2 { font-size: 20px; margin-top: 0; }
    .muted { color: #64748b; font-size: 13px; }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-top: 18px;
    }
    label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #475569;
    }
    input, select {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 14px;
      margin-top: 6px;
      outline: none;
    }
    input:focus, select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
    .btn-row {
      display: flex;
      gap: 12px;
      margin-top: 20px;
      flex-wrap: wrap;
    }
    button {
      background: var(--primary);
      color: white;
      border: 0;
      border-radius: 8px;
      padding: 11px 22px;
      font-weight: 700;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.2s;
    }
    button:hover { background: var(--primary-hover); }
    .btn-secondary {
      background: #f1f5f9;
      color: #334155;
      text-decoration: none;
      padding: 11px 18px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
    }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-danger {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }
    .btn-danger:hover { background: #fee2e2; }
    .result-box {
      border-radius: 14px;
      padding: 24px;
      margin-top: 10px;
    }
    .result-on {
      background: var(--green-bg);
      border: 1px solid #a7f3d0;
    }
    .result-off {
      background: var(--bg);
      border: 1px solid var(--border);
    }
    .result-fallback {
      background: var(--orange-bg);
      border: 1px solid #fde68a;
    }
    .status-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    .status-title h3 {
      margin: 0;
      font-size: 22px;
    }
    .mock-preview {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      margin-top: 18px;
    }
    pre {
      background: #0f172a;
      color: #e2e8f0;
      padding: 14px;
      border-radius: 8px;
      font-size: 12px;
      overflow-x: auto;
      margin: 10px 0 0;
    }
  </style>
</head>
<body>
<div class="wrap">

  <div class="header-bar">
    <div>
      <span class="badge badge-blue">ỨNG DỤNG CLIENT ĐỘC LẬP (PORT 8105)</span>
      <h1 style="margin-top:6px;">CommunityPass</h1>
    </div>
    <div style="text-align:right;">
      <span class="badge <?= $simulateError ? 'badge-red' : 'badge-green' ?>">
        <?= $simulateError ? '🔴 Đang giả lập mất kết nối API' : '🟢 Kết nối API: 127.0.0.1:8104' ?>
      </span>
    </div>
  </div>

  <!-- BẢNG ĐIỀU KHIỂN YÊU CẦU API -->
  <div class="card">
    <h2>1. Gọi HTTP API đánh giá cờ tính năng</h2>
    <p class="muted">
      Ứng dụng này chạy ở tiến trình độc lập (cổng 8105), gọi đến API của hệ thống FlagFlow (cổng 8104) kèm header <code>X-API-Key</code>.
    </p>

    <form method="get">
      <div class="form-grid">
        <div>
          <label>Mã người dùng (User Code)</label>
          <input name="user" value="<?=html($user)?>" required placeholder="user0001, dev, tester...">
        </div>
        <div>
          <label>Mã tính năng (Feature Key)</label>
          <input name="feature" value="<?=html($feature)?>" required placeholder="new_registration, smart_waitlist...">
        </div>
        <div>
          <label>Môi trường (Environment)</label>
          <select name="env">
            <option value="development" <?=$env==='development'?'selected':''?>>Development</option>
            <option value="beta" <?=$env==='beta'?'selected':''?>>Beta</option>
            <option value="production" <?=$env==='production'?'selected':''?>>Production</option>
          </select>
        </div>
      </div>

      <?php if($simulateError): ?>
        <input type="hidden" name="simulate_error" value="1">
      <?php endif; ?>

      <div class="btn-row">
        <button type="submit">🚀 Gửi yêu cầu HTTP API</button>
        <?php if(!$simulateError): ?>
          <a class="btn-secondary btn-danger" href="/?user=<?=html($user)?>&feature=<?=html($feature)?>&env=<?=html($env)?>&simulate_error=1">
            ⚡ Thử nghiệm API lỗi / Mất kết nối (Simulate Fallback)
          </a>
        <?php else: ?>
          <a class="btn-secondary" href="/?user=<?=html($user)?>&feature=<?=html($feature)?>&env=<?=html($env)?>">
            🔄 Khôi phục kết nối API bình thường
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- KẾT QUẢ ĐÁNH GIÁ & CƠ CHẾ DỰ PHÒNG FALLBACK -->
  <div class="card">
    <h2>2. Kết quả xử lý & Cơ chế dự phòng an toàn (Fail-closed)</h2>

    <?php if($decision['fallback']): ?>
      <!-- TRƯỜNG HỢP MẤT KẾT NỐI API -->
      <div class="result-box result-fallback">
        <div class="status-title">
          <h3 style="color:#b45309;">⚠️ KHÔNG THỂ LẤY CẤU HÌNH API → TỰ ĐỘNG FALLBACK: TẮT</h3>
          <span class="badge badge-orange">CHẾ ĐỘ DỰ PHÒNG (FALLBACK)</span>
        </div>
        <p style="margin:0; font-size:14px; color:#78350f;">
          <b>Xử lý sự cố:</b> Ứng dụng không thể kết nối đến máy chủ API tại <code><?=html($api)?></code>.
          Theo nguyên tắc bảo đảm tính sẵn sàng cao, ứng dụng <b>không bị dừng hay sập trang (Zero-crash)</b> mà tự động dùng giá trị mặc định an toàn: <b>TẮT TÍNH NĂNG</b>.
        </p>
        <p style="margin:8px 0 0; font-size:12px; color:#92400e;">
          Lỗi đã được tự động ghi nhận vào tệp nhật ký: <code>storage/client-errors.log</code>.
        </p>
      </div>

      <div class="mock-preview">
        <h4 style="margin:0 0 8px; color:#475569;">Giao diện hiển thị phía người dùng (Fallback):</h4>
        <p style="margin:0; font-size:14px; color:#64748b;">
          Đang sử dụng luồng đăng ký tiêu chuẩn cơ bản (An toàn khi máy chủ tính năng gặp sự cố).
        </p>
      </div>

    <?php else: ?>
      <!-- TRƯỜNG HỢP KẾT NỐI API THÀNH CÔNG -->
      <div class="result-box <?= $decision['enabled'] ? 'result-on' : 'result-off' ?>">
        <div class="status-title">
          <h3 style="color:<?= $decision['enabled'] ? '#065f46;' : '#475569;' ?>">
            <?= $decision['enabled'] ? '✨ TÍNH NĂNG ĐƯỢC BẬT (ENABLED)' : '🔒 TÍNH NĂNG ĐANG TẮT (DISABLED)' ?>
          </h3>
          <span class="badge <?= $decision['enabled'] ? 'badge-green' : 'badge-red' ?>">
            <?= $decision['enabled'] ? 'BẬT' : 'TẮT' ?>
          </span>
        </div>
        <p style="margin:0; font-size:14px;">
          <b>Phiên bản áp dụng:</b> #<?=html((string)$decision['version'])?> &nbsp;|&nbsp;
          <b>Lý do quyết định:</b> <code><?=html($decision['reason'])?></code>
        </p>
      </div>

      <div class="mock-preview">
        <h4 style="margin:0 0 8px; color:#475569;">Giao diện phản hồi tương ứng trên CommunityPass:</h4>
        <?php if($decision['enabled']): ?>
          <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:16px; border-radius:8px;">
            <b style="color:#166534;">🎉 Luồng tính năng mới đã được kích hoạt!</b>
            <p style="margin:6px 0 0; font-size:13px; color:#15803d;">
              Người dùng <code><?=html($user)?></code> đủ điều kiện trải nghiệm tính năng phiên bản mới trên môi trường <?=html(strtoupper($env))?>.
            </p>
          </div>
        <?php else: ?>
          <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px;">
            <b style="color:#475569;">ℹ️ Giao diện tiêu chuẩn (Mặc định)</b>
            <p style="margin:6px 0 0; font-size:13px; color:#64748b;">
              Tính năng đang tắt đối với người dùng <code><?=html($user)?></code> theo quy tắc phiên bản #<?=html((string)$decision['version'])?> (Lý do: <?=html($decision['reason'])?>).
            </p>
          </div>
        <?php endif; ?>
      </div>

      <?php if($lastRawResponse): ?>
        <label style="margin-top:16px;">Phản hồi JSON nguyên bản từ API (200 OK):</label>
        <pre><?=html(json_encode(json_decode($lastRawResponse), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- NHẬT KÝ LỖI CLIENT (LOGS) -->
  <div class="card">
    <h2>3. Nhật ký ghi nhận sự cố Client (storage/client-errors.log)</h2>
    <p class="muted">
      Khi có lỗi mạng, lỗi máy chủ hoặc API không phản hồi, ứng dụng ghi log độc lập để phục vụ kiểm toán và giám sát hệ thống.
    </p>
    <?php if(empty($recentLogs)): ?>
      <p class="muted" style="font-style:italic;">Chưa có lỗi nào được ghi nhận. Hệ thống đang hoạt động ổn định.</p>
    <?php else: ?>
      <pre><?php foreach($recentLogs as $line) echo html($line)."\n"; ?></pre>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
