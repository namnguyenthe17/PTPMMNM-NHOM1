<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/api/evaluate'){require __DIR__.'/api.php';exit;}
if(str_starts_with($path,'/assets/'))return false;
// Avatar: chỉ cho phép hình ảnh có tên do máy chủ cấp; không định tuyến thành HTML.
if(str_starts_with($path,'/uploads/')) {
    if(preg_match('~^/uploads/avatars/[a-f0-9]{32}\.(jpg|png|webp)$~D',(string)$path) && is_file(__DIR__.$path)) return false;
    http_response_code(404);exit('Not Found');
}
require __DIR__.'/index.php';
