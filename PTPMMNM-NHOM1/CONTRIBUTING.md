# Quy trình cộng tác

1. Mỗi thành viên tạo nhánh `feature/<ten-chuc-nang>` từ `main`, commit các thay đổi nhỏ và có mô tả.
2. Mở Issue mô tả lỗi/chức năng, dữ liệu tái hiện, kỳ vọng và kết quả thực tế.
3. Gửi Pull Request kèm ảnh/chạy test `bash lab.sh test`, chỉ rõ file tự viết và nguồn tham khảo.
4. Một thành viên khác review quyền truy cập phía máy chủ, validate dữ liệu, kết quả 0%/100%, rollback.
5. Sau review mới merge. Không commit `storage/`, token, mật khẩu, `.env`, ảnh chứa dữ liệu cá nhân.
6. Trước Release: chạy lại test, kiểm tra gói ZIP cài từ đầu và thử đủ hai tình huống trong README.
