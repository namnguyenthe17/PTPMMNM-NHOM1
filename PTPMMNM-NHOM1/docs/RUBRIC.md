# Đối chiếu rubric Đề tài 1

| Nhóm yêu cầu | Cơ chế trong mã | Kiểm tra |
|---|---|---|
| Dự án/thành viên/môi trường | `ProjectAccess`, menu Dự án, quyền viewer/editor/manager, admin | `tests/btl1.php` viewer |
| Flags/nhóm/tỷ lệ/ưu tiên | `FlagEngine.php`, trang Phiên bản & khôi phục | test 0%, 100%, allow, deny, cohort, ổn định |
| API và ứng dụng nhỏ | `public/api.php`, `demo/index.php`, `DemoClient.php` | Gọi thật 2 cổng; ngắt API kiểm tra fallback |
| Phiên bản/phát hành/khôi phục | `VersionService.php`, `release_history` | test bất biến/diff/rollback |
| Nhật ký/nhập/xuất | `audit`, `decision_logs`, `version.import/export`, JSON schema | test import đúng/sai, kiểm tra HTTP |
| Kỹ thuật 1 | SHA-256 dựa trên project/env/version/flag/user, cố định | 0%,100%, ổn định, allow/deny |
| Kỹ thuật 2 | rollback tạo bản published mới, demo fallback off | test rollback + client lỗi API |
| Dữ liệu | script sinh 13 flags / 3 groups / 1000 fake user IDs | `php scripts/upgrade-btl1.php` |
| CSDL | `MySqlStore` (SQL aggregate store) | **Cần cấu hình MySQL riêng; chưa chuẩn hóa bảng** |
| GitHub/nhóm | LICENSE, README, CONTRIBUTING, issues | Tạo issue, review và Release thật |

## Những việc cần được giảng viên xác nhận

- Thiết kế MySQL hiện sử dụng một JSON document với transaction/row lock. Nếu môn yêu cầu mô hình quan hệ nhiều bảng thì cần tiếp tục chuẩn hóa.
- Việc dùng mã nguồn và trợ lý lập trình AI nên được nhóm ghi nguồn minh bạch.
- Thầy chấm 1 điểm đóng góp cá nhân bằng giải thích và sửa nhỏ ngay tại buổi bảo vệ.
