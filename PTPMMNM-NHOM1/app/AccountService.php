<?php
declare(strict_types=1);
final class AccountService {
    public const ROLES = ['admin', 'developer', 'tester'];
    public static function create(array &$db,string $username,string $name,string $password,string $role): int {
        $username=trim($username); $name=trim($name);
        if (!preg_match('/^[a-z][a-z0-9_]{2,29}$/',$username)) throw new DomainException('Tên đăng nhập: 3–30 ký tự, chữ thường, số hoặc dấu _');
        if (mb_strlen($name)<2 || mb_strlen($name)>80) throw new DomainException('Họ tên phải từ 2 đến 80 ký tự');
        if (!in_array($role,self::ROLES,true)) throw new DomainException('Vai trò không hợp lệ');
        if (strlen($password)<10 || strlen($password)>128) throw new DomainException('Mật khẩu phải từ 10 đến 128 ký tự');
        foreach($db['users'] as $row) if($row['username']===$username) throw new DomainException('Tên đăng nhập đã tồn tại');
        $id=Store::next($db,'users');
        $db['users'][]=['id'=>$id,'username'=>$username,'name'=>$name,'role'=>$role,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)];
        return $id;
    }
    public static function changePassword(array &$db,int $id,string $password): void {
        if(strlen($password)<10 || strlen($password)>128) throw new DomainException('Mật khẩu phải từ 10 đến 128 ký tự');
        foreach($db['users'] as &$row) if($row['id']===$id){$row['password_hash']=password_hash($password,PASSWORD_DEFAULT);return;}
        throw new DomainException('Không tìm thấy tài khoản');
    }
}
