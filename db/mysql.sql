-- Chạy sau khi tạo DATABASE flagflow CHARACTER SET utf8mb4.
-- Dữ liệu được giữ trong một document JSON có giao dịch; bước chuẩn hóa bảng quan hệ
-- là hướng mở rộng, không được nhận là thiết kế bảng chuẩn hóa đầy đủ.
CREATE TABLE IF NOT EXISTS app_state (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  payload_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT valid_payload CHECK (JSON_VALID(payload_json))
) ENGINE=InnoDB;
