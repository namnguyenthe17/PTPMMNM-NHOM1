<?php
declare(strict_types=1);
/** API failures are intentionally fail-closed (fallback=false) and logged. */
final class DemoClient {
    public static function decide(callable $request, callable $log): array {
        try {
            $raw=$request();
            if(!is_string($raw)||$raw==='')throw new RuntimeException('API không phản hồi');
            $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($data)||!array_key_exists('enabled',$data)||!is_bool($data['enabled']))throw new RuntimeException('Phản hồi API sai cấu trúc');
            return ['enabled'=>$data['enabled'],'fallback'=>false,'version'=>$data['version']??null,'reason'=>$data['reason']??'unknown'];
        } catch(Throwable $e) {
            $log('API lỗi, chuyển fallback OFF: '.$e->getMessage());
            return ['enabled'=>false,'fallback'=>true,'version'=>null,'reason'=>'api-unavailable'];
        }
    }
}
