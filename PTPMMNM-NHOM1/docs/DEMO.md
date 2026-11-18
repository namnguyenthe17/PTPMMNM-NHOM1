# Kịch bản bảo vệ BTL Đề tài 1

**Trước demo:** `bash lab.sh test` và giữ hai terminal chạy `bash lab.sh run` (API:8104) và `bash lab.sh demo` (demo:8105).

1. Đăng nhập Developer: tạo tính năng, chọn Beta, gửi Admin, cho thấy `pending` và Beta chưa đổi.
2. Đăng nhập Admin: thấy yêu cầu, duyệt, cho thấy cấu hình Beta có bản phát hành mới.
3. Tại trang **Phiên bản & khôi phục**, tạo nháp, chọn tỷ lệ 0%, lưu, Admin phát hành và xem API trả false.
4. Nháp tiếp tỷ lệ 100%, phát hành, API trả true. Nhập `user0001` từ danh sách allow, `user0002` từ deny để chứng minh ưu tiên.
5. Tạo nháp 30%, phát hành. Gọi API 3 lần với cùng user/version trả kết quả giống nhau.
6. Khôi phục một bản cũ: cho thấy version ID mới xuất hiện, lịch sử cũ còn và API trở về giá trị trước đó.
7. Mở demo ở 8105; tắt máy chủ API ở 8104 và tải lại demo: fallback OFF, không sập, sinh `storage/client-errors.log`.
8. Đăng nhập quyền Viewer và thử gửi POST sửa trái quyền: bị từ chối tại PHP, không chỉ ẩn nút.

**Lưu ý:** Dùng `user0001` ... `user1000` là mã người dùng giả. Không dùng mật khẩu hoặc token thật để chụp ảnh.
