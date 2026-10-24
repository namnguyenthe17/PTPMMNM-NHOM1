<?php
declare(strict_types=1);
/** Transactional SQL-backed aggregate. MySQL must be explicitly enabled via env. */
final class MySqlStore {
    private PDO $pdo;
    public function __construct() {
        $dsn=(string)getenv('FLAGFLOW_DB_DSN');
        if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Chỉ cho phép DSN mysql');
        $this->pdo=new PDO($dsn,(string)getenv('FLAGFLOW_DB_USER'),(string)getenv('FLAGFLOW_DB_PASSWORD'),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
        ]);
    }
    public function read(): array {
        $stmt=$this->pdo->query('SELECT payload_json FROM app_state WHERE id = 1');
        $raw=$stmt->fetchColumn();
        if(!is_string($raw))throw new RuntimeException('MySQL chưa có dữ liệu, chạy script chuyển dữ liệu');
        return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    }
    public function transaction(callable $callback): mixed {
        $this->pdo->beginTransaction();
        try {
            $raw=$this->pdo->query('SELECT payload_json FROM app_state WHERE id=1 FOR UPDATE')->fetchColumn();
            if(!is_string($raw))throw new RuntimeException('Chưa khởi tạo MySQL');
            $db=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            $result=$callback($db);
            $json=json_encode($db,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $stmt=$this->pdo->prepare('UPDATE app_state SET payload_json=:payload,revision=revision+1 WHERE id=1');
            $stmt->execute([':payload'=>$json]);
            $this->pdo->commit();
            return $result;
        } catch(Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }
}
