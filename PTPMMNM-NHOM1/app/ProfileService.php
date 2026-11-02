<?php
declare(strict_types=1);

/** Tài khoản cá nhân. Không cấp/chỉnh sửa role qua các biểu mẫu hồ sơ. */
final class ProfileService {
    public static function findUser(array $db, int $userId): array {
        foreach ($db['users'] ?? [] as $row) {
            if ((int)$row['id'] === $userId) return $row;
        }
        throw new DomainException('Tài khoản không tồn tại');
    }

    public static function updateInfo(array &$db, int $userId, string $name, string $email, string $bio): void {
        $name = trim($name);
        $email = trim($email);
        $bio = trim($bio);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) throw new DomainException('Họ tên phải từ 2 đến 80 ký tự');
        if ($email !== '' && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new DomainException('Email không hợp lệ');
        if (mb_strlen($bio) > 300) throw new DomainException('Giới thiệu không quá 300 ký tự');
        foreach ($db['users'] as &$row) {
            if ((int)$row['id'] !== $userId) continue;
            $row['name'] = $name;
            $row['email'] = $email;
            $row['bio'] = $bio;
            $row['profile_updated_at'] = date('c');
            return;
        }
        throw new DomainException('Tài khoản không tồn tại');
    }

    public static function changePassword(array &$db, int $userId, string $current, string $new, string $confirm): void {
        $account = self::findUser($db, $userId);
        if (!password_verify($current, (string)$account['password_hash'])) throw new DomainException('Mật khẩu hiện tại không chính xác');
        if ($new !== $confirm) throw new DomainException('Xác nhận mật khẩu mới không khớp');
        if (password_verify($new, (string)$account['password_hash'])) throw new DomainException('Mật khẩu mới phải khác mật khẩu hiện tại');
        AccountService::changePassword($db, $userId, $new);
    }

    /** Avatar chỉ được trỏ đến các tệp upload do hệ thống tự đặt tên. */
    public static function avatarUrl(?string $value): string {
        $value = (string)$value;
        return preg_match('~^/uploads/avatars/[a-f0-9]{32}\\.(jpg|png|webp)$~D', $value) ? $value : '';
    }

    public static function updateAvatar(array &$db, int $userId, string $url): string {
        if (self::avatarUrl($url) !== $url || $url === '') throw new DomainException('Đường dẫn ảnh không hợp lệ');
        foreach ($db['users'] as &$row) {
            if ((int)$row['id'] !== $userId) continue;
            $old = self::avatarUrl((string)($row['avatar'] ?? ''));
            $row['avatar'] = $url;
            $row['profile_updated_at'] = date('c');
            return $old;
        }
        throw new DomainException('Tài khoản không tồn tại');
    }

    public static function removeAvatar(array &$db, int $userId): string {
        foreach ($db['users'] as &$row) {
            if ((int)$row['id'] !== $userId) continue;
            $old = self::avatarUrl((string)($row['avatar'] ?? ''));
            $row['avatar'] = '';
            $row['profile_updated_at'] = date('c');
            return $old;
        }
        throw new DomainException('Tài khoản không tồn tại');
    }

    /** Chỉ chấp nhận ảnh được PHP xác nhận thực sự là JPG/PNG/WebP, max 2MB. */
    public static function receiveImage(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new DomainException('Chưa chọn ảnh hoặc tải ảnh lên bị lỗi');
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > 2 * 1024 * 1024) throw new DomainException('Ảnh đại diện tối đa 2 MB');
        if (!is_uploaded_file($tmp)) throw new DomainException('Tệp tải lên không hợp lệ');
        if (!class_exists('finfo')) throw new RuntimeException('Máy chủ chưa bật PHP Fileinfo');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$mime])) throw new DomainException('Chỉ nhận ảnh JPG, PNG hoặc WebP');
        $image = @getimagesize($tmp);
        if (!$image || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1 || $image[0]>5000 || $image[1]>5000) {
            throw new DomainException('Tệp ảnh bị lỗi hoặc có kích thước không hợp lệ');
        }
        $dir = ROOT . '/public/uploads/avatars';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Không thể tạo thư mục lưu ảnh');
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Không thể lưu ảnh đại diện');
        @chmod($dest, 0644);
        return '/uploads/avatars/' . $filename;
    }

    public static function deleteLocalAvatar(string $url): void {
        $safe = self::avatarUrl($url);
        if ($safe !== '') {
            $path = ROOT . '/public' . $safe;
            if (is_file($path)) @unlink($path);
        }
    }
}
