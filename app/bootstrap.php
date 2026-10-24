<?php
declare(strict_types=1);
const ROOT=__DIR__.'/..';
if (file_exists(ROOT.'/.env')) {
    foreach (file(ROOT.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (getenv($k) === false || getenv($k) === '') {
                putenv("$k=$v");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}
require_once __DIR__.'/Store.php';
require_once __DIR__.'/MySqlStore.php';
require_once __DIR__.'/Policy.php';
require_once __DIR__.'/AccountService.php';
require_once __DIR__.'/FeatureSubmission.php';
require_once __DIR__.'/FlagEngine.php';
require_once __DIR__.'/VersionService.php';
require_once __DIR__.'/ProjectAccess.php';
require_once __DIR__.'/ApiService.php';
require_once __DIR__.'/DemoClient.php';
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return preg_match_all('/./us',$s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s,int $start,int $length): string { preg_match_all('/./us',$s,$m); return implode('',array_slice($m[0],$start,$length)); } }
function store(): Store|MySqlStore { return getenv('FLAGFLOW_DB_DSN') ? new MySqlStore() : new Store(ROOT.'/storage/data.json'); }
function user(): ?array { return $_SESSION['user']??null; }
function logged(): bool { return user()!==null; }
function is_admin(): bool { return (user()['role']??'')==='admin'; }
function can_edit(): bool { return in_array(user()['role']??'',['admin','developer'],true); }
function h(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function redirect(string $to='dashboard'): never { header('Location: /?p='.rawurlencode($to),true,303); exit; }
function csrf(): string { if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; }
function alert(string $message,string $type='ok'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function ident(string $value): bool { return (bool)preg_match('/^[a-z][a-z0-9_]{2,39}$/',$value); }
function get_by_id(array $db,string $table,int $id): array { return Policy::find($db[$table],$id)??throw new DomainException('Không tìm thấy dữ liệu'); }
function audit(array &$db,string $action,string $detail): void { $db['audit'][]=['id'=>Store::next($db,'audit'),'user_id'=>user()['id'],'action'=>$action,'detail'=>$detail,'at'=>date('c')]; }
function valid_group(array $db,int $projectId,?int $groupId): void { if ($groupId!==null && get_by_id($db,'groups',$groupId)['project_id']!==$projectId) throw new DomainException('Nhóm không thuộc dự án này'); }
function set_config(array &$db,int $featureId,string $env,bool $enabled,?int $groupId): void {
    foreach($db['configs'] as &$row) if($row['feature_id']===$featureId && $row['environment']===$env) { $row['enabled']=$enabled; $row['group_id']=$groupId; $row['updated_at']=date('c'); return; }
    unset($row);
    $db['configs'][]=['id'=>Store::next($db,'configs'),'feature_id'=>$featureId,'environment'=>$env,'enabled'=>$enabled,'group_id'=>$groupId,'updated_at'=>date('c')];
}
