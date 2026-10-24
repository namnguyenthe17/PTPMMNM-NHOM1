# FlagFlow – BTL Đề tài 1: Quản lý cấu hình bật/tắt tính năng Web

Dự án PHP 8.1+ phát triển dựa trên kiến thức Lab 01 (Ubuntu, Bash, Git) và Lab 02 (quy tắc PHP, phân lớp, kiểm thử). Bản mở rộng bám sát **Đề tài 1** trong `DE_TAI_BTL(1).docx`, trang 6–7.

> **Cảnh báo:** Dùng hoàn toàn dữ liệu giả. Đây là mô hình đồ án; cần gia cố xác thực, quản lý khóa, rate limit, HTTPS và cơ sở dữ liệu chuẩn hóa trước khi cung cấp dịch vụ công khai.

## Nghiệp vụ

- Vai trò: `admin` (phê duyệt/phát hành/khôi phục), `developer` (tạo tính năng/gửi yêu cầu, sửa nháp khi có quyền), `tester` (quyền xem, tự đăng ký). Admin cấp quyền riêng `viewer`, `editor`, `manager` theo từng dự án.
- 3 môi trường: **Development**, **Beta**, **Production**. Developer bật tính năng ở Development; yêu cầu thay đổi Beta/Production chuyển đến Admin và chỉ áp dụng sau khi duyệt (không được tự duyệt đề xuất).
- Nhóm thử nghiệm; quy tắc ưu tiên: **tắt toàn cục → danh sách chặn → danh sách cho phép → điều kiện thuộc nhóm → tỷ lệ 0–100%**. Tỷ lệ dựa trên SHA-256 và mã người dùng, không dùng `rand()`: cùng `(project, environment, version, feature, user_code)` luôn cùng kết quả.
- Bản nháp cho phép sửa tỷ lệ và danh sách ưu tiên. Bản đã phát hành bất biến; khôi phục bản cũ **tạo thêm bản phát hành mới**, giữ nguyên các bản cũ và lịch sử.
- API `GET /api/evaluate` dùng khóa `X-API-Key`, trả `{enabled, version, reason}`. Ứng dụng mẫu **khác tiến trình** gọi API qua HTTP; khi API lỗi sẽ dùng `enabled=false` và ghi log, không sập ứng dụng.
- Nhập/xuất cấu hình JSON (kiểm tra schema, dự án, môi trường, tệp ≤150KB). Nhập chỉ tạo nháp chứ không tự phát hành.
- Dữ liệu mẫu: ít nhất **10 flags**, **3 nhóm**, **1.000 mã người dùng giả**, 3 môi trường (script tạo 13 flags).

## Cài đặt và chạy trên Ubuntu WSL (không cần tải lại dữ liệu cũ)

```bash
cd ~/FeatureFlag_v3/FeatureFlag_Manager     # thay bằng thư mục dự án của bạn
sudo apt update
sudo apt install -y php-cli php-mbstring git unzip

# Chỉ lần đầu khi storage/data.json chưa tồn tại:
php scripts/setup.php

# Nâng cấp dữ liệu cũ, giữ nguyên tài khoản/mật khẩu/yêu cầu duyệt:
php scripts/upgrade-btl1.php

bash lab.sh check
bash lab.sh test
bash lab.sh run
```

Mở `http://127.0.0.1:8104`. Trong terminal thứ hai chạy:

```bash
cd <thư_mục_dự_án>
bash lab.sh demo
```

Mở `http://127.0.0.1:8105` để xem **ứng dụng độc lập** gọi HTTP API. Khi muốn kiểm tra fallback, dừng API ở cổng 8104 bằng `Ctrl+C`, giữ demo ở 8105 và bấm lại `Gọi API`. Kết quả phải **TẮT (fallback)** và lỗi được ghi vào `storage/client-errors.log`.

**Tài khoản:** `admin`, `dev`, `tester`. Mật khẩu sinh riêng khi `php scripts/setup.php`, không có mật khẩu mặc định. Nếu quên mật khẩu demo, có thể chạy `php scripts/reset-demo-accounts.php --confirm`, nhưng phải giữ mật khẩu mới kín.

## Hai tình huống nghiệm thu

### Tình huống 1: tạo → kiểm tra → phát hành → gọi API

1. Login `dev` → **Gửi tính năng mới** → chọn CommunityPass, `beta`, nhập mã/tên/lý do → gửi.
2. Login `admin` → **Phê duyệt** → đọc lý do, nhóm, trạng thái → **Duyệt** (Developer không có quyền duyệt).
3. Login Admin → **Phiên bản & khôi phục** → tạo nháp mới trên Beta → sửa tỷ lệ 30%, danh sách ưu tiên, chọn nhóm → phát hành.
4. Mở app demo ở cổng 8105, nhập mã `user0001` và feature key; API trả `enabled=true|false` gắn đúng phiên bản đã công bố.

### Tình huống 2: đổi tỷ lệ → so sánh → khôi phục → kiểm tra app

1. Admin tạo nháp Beta mới, đổi tỷ lệ 30% thành 0%, phát hành.
2. Trên trang phiên bản xem chi tiết, phần **so sánh** hiển thị cấu hình trước/sau.
3. Chọn bản trước đó và **Khôi phục bản này thành phiên bản mới** (nhập lý do).
4. Kiểm tra API/ứng dụng mẫu: quay về trạng thái của bản cũ, nhưng số phiên bản mới tăng và lịch sử không mất.

## API

```bash
# Khóa API được tạo riêng khi chạy scripts/upgrade-btl1.php, đặt trong storage/api-key.txt
API_KEY="$(cat storage/api-key.txt)"
curl -fsS -H "X-API-Key: $API_KEY" \
 'http://127.0.0.1:8104/api/evaluate?project=communitypass&env=beta&feature=new_registration&user=user0001'
unset API_KEY
```

Có thể thêm tham số `version=7` (ID bản phát hành) để kiểm tra lại một phiên bản lịch sử. Request không có token bị từ chối HTTP 401. Phiên bản chưa phát hành không được cung cấp qua API.

## Kết quả kiểm thử dự kiến

`bash lab.sh test` gồm 10 ca chính, 7 ca tài khoản, 10 ca gửi duyệt và **32 ca kỹ thuật của Đề tài 1**. Hãy chạy trực tiếp trên máy nộp và chụp kết quả. Các phép thử gồm: 0%, 100%, ổn định, ưu tiên, quyền xem/sửa, tính bất biến, khác biệt phiên bản, khôi phục, API fallback, JSON sai định dạng.

## Lưu trữ và yêu cầu MySQL

Đề yêu cầu PHP cùng **MySQL/PostgreSQL**. Bộ mã nguồn có **hai chế độ**:

1. **JSON cục bộ**: mặc định, để chuyển từ bản cũ không mất dữ liệu và thử giao diện nhanh. **Chế độ này chưa đạt tiêu chí cơ sở dữ liệu của bài tập lớn nếu không được giảng viên duyệt.**
2. **MySQL**: khi có `FLAGFLOW_DB_DSN` kết nối, chương trình sử dụng `app/MySqlStore.php` với PDO, transaction và row lock. Hiện lưu toàn bộ dữ liệu ứng dụng dạng một JSON document trong một bảng SQL; **đã là MySQL nhưng chưa chuẩn hóa thành các bảng riêng** như dự án/thành viên/quy tắc/phiên bản. Nếu thầy yêu cầu thiết kế quan hệ chuẩn hóa thì phải tiếp tục cải tiến.

Ví dụ thiết lập MySQL sau khi đã có dữ liệu JSON:

```bash
sudo apt install -y mysql-server php-mysql
# Tạo database và user riêng trong MySQL với quyền tối thiểu.
export FLAGFLOW_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=flagflow;charset=utf8mb4'
export FLAGFLOW_DB_USER='flagflow'
read -srp 'MySQL password: ' FLAGFLOW_DB_PASSWORD; echo
export FLAGFLOW_DB_PASSWORD
php scripts/migrate-to-mysql.php
bash lab.sh test
bash lab.sh run
```

Không ghi mật khẩu vào file `.env` rồi commit; không đưa file `storage/` lên GitHub. Chương trình cố ý **không ghi đè** nếu MySQL đã có dữ liệu. MySQL cần được tạo database + quyền sử dụng trước khi chạy lệnh trên.

## Cấu trúc

- `app/FlagEngine.php`: thuật toán ổn định + ưu tiên.
- `app/VersionService.php`: tạo nháp, phát hành, so sánh, khôi phục, nhập/xuất cấu hình.
- `app/ProjectAccess.php`: quyền theo dự án; server từ chối thao tác trái quyền.
- `app/ApiService.php`, `public/api.php`: API gắn project/env/version.
- `app/DemoClient.php`, `demo/index.php`: demo API lỗi/fallback.
- `app/FeatureSubmission.php`: Developer gửi tính năng; Admin xét duyệt theo luồng cũ.
- `app/Store.php`: JSON cục bộ; `app/MySqlStore.php`: dữ liệu trong giao dịch MySQL.
- `scripts/upgrade-btl1.php`: nâng cấp dữ liệu cũ không làm mất người dùng.
- `tests/btl1.php`: ca kiểm thử bám mục 6 đề tài 1.
- `docs/RUBRIC.md`, `docs/DEMO.md`: đối chiếu tiêu chí và kịch bản bảo vệ.

## Quyền tác giả, nguồn và cộng tác

Mã do nhóm phát triển với sự hỗ trợ lập trình của ChatGPT. Các mã mẫu từ Lab 1/2 chỉ được dùng làm định hướng kỹ thuật; không sao chép thư viện đóng nguồn. CSS tự xây dựng, không nạp CDN. Xem `LICENSE`, `CONTRIBUTING.md`, `.github/ISSUE_TEMPLATE/bug_report.md` và `docs/RELEASE_NOTES.md`.

**Trước nộp:** tạo repository GitHub riêng cho BTL, commit README+LICENSE+CONTRIBUTING, ít nhất một issue và pull request có review, tạo Release gắn commit cuối cùng, ghi tên thành viên/đóng góp và kiểm thử trên máy khác. Không phát hành code demo bằng PHP built-in server trên Internet công khai.
