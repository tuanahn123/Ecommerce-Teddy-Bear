## Hướng dẫn Cài đặt Laravel

### Cài đặt dự án

> composer install

### Cấu hình môi trường

> cp .env.example .env  
> php artisan key:generate

### Cấu hình cơ sở dữ liệu

Chỉnh sửa file `.env`:

> DB_CONNECTION=mysql
> DB_HOST=127.0.0.1
> DB_PORT=3306
> DB_DATABASE=ecommerce_teddy_bear
> DB_USERNAME=root
> DB_PASSWORD=

### Chạy migration

> php artisan migrate

### Chạy storage:link

> php artisan storage:link

### Chạy server

> php artisan serve
