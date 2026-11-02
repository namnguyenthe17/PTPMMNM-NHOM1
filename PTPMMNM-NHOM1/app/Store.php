<?php
declare(strict_types=1);
final class Store {
    public function __construct(private string $file) {}
    public function read(): array {
        if (!is_file($this->file)) throw new RuntimeException('Chưa khởi tạo dữ liệu. Chạy php scripts/setup.php');
        $fp=fopen($this->file,'rb'); if (!$fp) throw new RuntimeException('Không thể mở dữ liệu');
        try { flock($fp,LOCK_SH); $raw=stream_get_contents($fp); flock($fp,LOCK_UN); }
        finally { fclose($fp); }
        $data=json_decode($raw,true); if (!is_array($data)) throw new RuntimeException('Dữ liệu không hợp lệ'); return $data;
    }
    public function transaction(callable $callback): mixed {
        $lock=fopen($this->file.'.lock','c'); if (!$lock) throw new RuntimeException('Không thể tạo khóa dữ liệu');
        try {
            if (!flock($lock,LOCK_EX)) throw new RuntimeException('Không thể khóa dữ liệu');
            $data=$this->read(); $result=$callback($data);
            $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $tmp=tempnam(dirname($this->file),'txn_'); if (!$tmp) throw new RuntimeException('Không thể tạo tệp tạm');
            try { file_put_contents($tmp,$json,LOCK_EX); chmod($tmp,0600); if (!rename($tmp,$this->file)) throw new RuntimeException('Không thể lưu dữ liệu'); }
            finally { if(is_file($tmp)) unlink($tmp); }
            flock($lock,LOCK_UN); return $result;
        } finally { fclose($lock); }
    }
    public static function next(array &$db,string $collection): int {
        $max=0; foreach($db[$collection] as $row) $max=max($max,(int)$row['id']); return $max+1;
    }
}
