<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/api/evaluate'){require __DIR__.'/api.php';exit;}
if(str_starts_with($path,'/assets/'))return false;
require __DIR__.'/index.php';
