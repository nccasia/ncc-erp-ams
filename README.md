## Tool ERP-AMS ( Tool quản lý thiết bị văn phòng)

Đây là tool quản lý thiết bị văn phòng cho nội bộ công ty NCC. Tool dùng để quản lý công ty có thiết bị gì , thuộc văn phòng nào , cấp phát cho nhân viên nào 

Tool được build bằng [Laravel 8](http://laravel.com).

Tool ERP-AMS được quản lý và cập nhật thường xuyên bởi đội ngũ nhân viên của NCC

-----

### Installation

1. Cài đặt các thư viện của dự án thông qua [Composer](https://getcomposer.org/) và [NPM](https://nodejs.org/en/download/current)
```

	composer install 

	&& 

	npm install

```

2. Sao chép file `.env.example` và đổi tên thành `.env` để cài đặt các biến môi trường.

3. Khởi tạo APP_KEY cho dự án
```

	php artisan key:generate

```

4. Khởi tạo DB cho dự án
```

	php artisan migrate --seed

```

5. Khởi tạo token để authenticate
```

	php artisan passport:install

```

6. Chạy server 
```

	php artisan serve

```

-----

### Tests

Dự án có hỗ trợ testing bằng thư viện [Codeception](https://codeception.com/).
Chạy command để build các Tester
```

	php codecept.phar build

```
#### UNIT TEST
Chạy command để test các Unit Test trong thư mục `/tests/Unit`
```

	php codecept.phar run unit

```

#### API TEST 
Chạy command để test các API Test trong thư mục `/tests/api`
```

	php codecept.phar run api

```

#### PRE-COMMIT TEST
Dự án có cài đặt [Husky](https://typicode.github.io/husky/) để chạy test trước khi commit lên Github. Husky được config ở trong file `package.json`
```

	"husky": {
        "hooks": {
            "pre-commit": "php codecept.phar run api,unit"
        }
    }

```