<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if (PHP_SAPI!=='cli') exit(1);
if (($argv[1]??'')!=='--confirm') {
    fwrite(STDERR,"Lệnh này thay đổi mật khẩu của admin, dev, tester. Chạy: php scripts/reset-demo-accounts.php --confirm\n");
    exit(2);
}
$presets=[['admin','Quản trị viên','admin'],['dev','Lập trình viên','developer'],['tester','Người thử nghiệm','tester']];
$results=store()->transaction(function(array &$db)use($presets):array {
    $result=[];
    foreach($presets as [$username,$name,$role]) {
        $password=bin2hex(random_bytes(12)); $found=null;
        foreach($db['users'] as $row) if($row['username']===$username){$found=$row['id'];break;}
        if($found===null) AccountService::create($db,$username,$name,$password,$role);
        else AccountService::changePassword($db,(int)$found,$password);
        $result[$username]=$password;
    }
    return $result;
});
echo "Đã tạo/cập nhật 3 tài khoản (không xóa dữ liệu dự án). LƯU MẬT KHẨU RIÊNG, KHÔNG CHỤP/GỬI/GIT COMMIT:\n";
foreach($results as $name=>$pass)echo "$name: $pass\n";
