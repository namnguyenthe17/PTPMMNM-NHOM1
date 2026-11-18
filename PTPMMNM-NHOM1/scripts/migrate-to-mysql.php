<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit(2);
$path=__DIR__.'/../storage/data.json';
if(!is_file($path)){fwrite(STDERR,"Cần storage/data.json đã có dữ liệu.\n");exit(2);}
$dsn=(string)getenv('FLAGFLOW_DB_DSN');
if(!str_starts_with($dsn,'mysql:')){fwrite(STDERR,"Thiếu FLAGFLOW_DB_DSN mysql.\n");exit(2);}
$json=file_get_contents($path);
json_decode($json,true,512,JSON_THROW_ON_ERROR);
$pdo=new PDO($dsn,(string)getenv('FLAGFLOW_DB_USER'),(string)getenv('FLAGFLOW_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents(__DIR__.'/../db/mysql.sql'));
$pdo->beginTransaction();
try {
    $rows=(int)$pdo->query('SELECT COUNT(*) FROM app_state')->fetchColumn();
    if($rows>0)throw new RuntimeException('MySQL đã chứa dữ liệu; từ chối ghi đè');
    $stmt=$pdo->prepare('INSERT INTO app_state (id,payload_json,revision) VALUES (1,?,0)');
    $stmt->execute([$json]);$pdo->commit();
    echo "Chuyển dữ liệu lên MySQL thành công. File JSON gốc được giữ để sao lưu.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
