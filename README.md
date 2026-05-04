DANH SÁCH THÀNH VIÊN
1. Nguyễn Đăng Khoa - 52400018
2. Phan Văn Tây - 52400236
3. Phan Văn Dương - 52400009

KIẾN TRÚC HỆ THỐNG VÀ TECH STACK

1. Kiến trúc hệ thống:
- Mô hình MVC (Model - View - Controller):
  + Model: Đảm nhiệm tương tác với cơ sở dữ liệu (sử dụng Eloquent ORM của Laravel) để quản lý các thực thể như User, Note.
  + View: Sử dụng Blade Templates để render giao diện động phía server.
  + Controller: Xử lý logic nghiệp vụ, điều hướng request từ người dùng đến đúng Model và View tương ứng.
- Giao tiếp thời gian thực (Real-time): Ứng dụng WebSockets để tự động cập nhật trạng thái dữ liệu trên giao diện ngay lập tức mà không cần tải lại trang.
- Quản lý tài nguyên đám mây (Cloudinary): Xử lý việc upload, tối ưu hóa và lưu trữ hình ảnh đính kèm một cách an toàn và tối ưu băng thông.
- Các cơ chế bảo mật cốt lõi:
  + HMAC Security: Đảm bảo tính toàn vẹn và xác thực của dữ liệu/yêu cầu.
  + Xác thực Email (SMTP): Gửi email xác nhận đăng ký tài khoản hoặc khôi phục mật khẩu.
  + Quản lý phiên (Session) & CSRF Protection: Chống lại các cuộc tấn công giả mạo yêu cầu.
- Tối ưu hiệu năng Frontend: Áp dụng kỹ thuật Debounce trong thanh tìm kiếm và nhập liệu để hạn chế số lượng request liên tục gửi lên server.

2. Tech Stack (Công nghệ sử dụng):
- Backend:
  + Framework: Laravel (PHP >= 8.1).
  + Tương tác CSDL: Eloquent ORM.
  + Các thư viện khác: Cloudinary PHP SDK, package hỗ trợ WebSockets, Mailer (SMTP).
- Frontend:
  + Template Engine: Laravel Blade.
  + Ngôn ngữ: HTML5, CSS3, JavaScript (ES6+).
  + Công cụ Build: Vite (Hỗ trợ đóng gói module và Hot Module Replacement - HMR).
- Cơ sở dữ liệu: MySQL / MariaDB.
- Môi trường & Triển khai (DevOps):
  + Local Development: Docker & Docker Compose (sử dụng `docker-compose.yml`).
  + Production Deployment: Render (sử dụng `Dockerfile.render`).

HƯỚNG DẪN CÀI ĐẶT VÀ CẤU HÌNH (NOTE APP - LARAVEL)

Yêu cầu hệ thống:
- PHP >= 8.1
- Composer
- Node.js & npm
- MySQL / MariaDB
- Docker (Tùy chọn)

CÁCH 1: CÀI ĐẶT THỦ CÔNG

*Lưu ý: File `.env` đã được nhóm cấu hình sẵn các thông tin cần thiết (Database, SMTP Mail, Cloudinary...), do đó bạn không cần phải thiết lập lại.*

Bước 1: Di chuyển vào thư mục dự án
cd note-app

Bước 2: Cài đặt các thư viện PHP và Javascript
composer install
npm install

Bước 3: Tạo khóa ứng dụng (Application Key)
php artisan key:generate

Bước 4: Tạo các bảng trong Database (Chạy migration)
php artisan migrate

Bước 5: Biên dịch các tệp tin CSS/JS (Vite)
npm run build

Bước 6: Khởi chạy ứng dụng
php artisan serve
Ứng dụng sẽ có thể truy cập tại: http://localhost:8000


CÁCH 2: CHẠY BẰNG DOCKER (Nhanh nhất)

Bước 1: Di chuyển vào thư mục dự án
cd note-app

Bước 2: Khởi chạy các container (tự động cài đặt mọi thứ)
docker-compose up -d --build

Bước 3: Chạy migration để tạo bảng cơ sở dữ liệu
docker-compose exec app php artisan migrate

Ứng dụng sẽ có thể truy cập tại: http://localhost (hoặc http://localhost:80 tùy thiết lập máy)
