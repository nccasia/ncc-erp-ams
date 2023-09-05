# ERP-AMS Back-end




## Overview
The ERP-AMS is an open-source application used to track and manage the company's office equipment and employees to optimize management time. It includes various functions such as inventory management, confirming equipment allocation or retrieval with email notifications, monitoring activities within the application, and managing employees' personal devices. This repository contains the back-end code of ams.

## Table of Contents

- [Overview](#overview)
- [Table of Contents](#table-of-contents)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Backend Setup](#backend-setup)
  - [Running](#running)
  - [Testing](#testing)

## Getting Started

### Prerequisites

Before you begin, ensure you have met the following requirements:

- [Visual Studio Code](https://code.visualstudio.com/download) installed.
- [PHP 8.1](https://www.php.net/downloads) installed.
- [Composer 2.4.2](https://getcomposer.org/download/) installed.
- [MySql 8.0](https://dev.mysql.com/downloads/installer/) installed.
- [NodeJS](https://nodejs.org/en/download) installed

### Backend Setup

1. **Create a folder** to store the backend code.
- example:  folder `erp-ams`

2. **Open a command prompt** in the created folder.

3. **Clone the backend repository** using the following command:

   ```bash
   git clone https://github.com/ncc-erp/ncc-erp-ams
   ```

4. Create local database:
- Create new databse in `MySql server`.

5. Open the backend using **Visual Studio Code**:
- Open `Visual Studio Code`.
- Navigate to the folder `erp-ams` and open the file.

6. **Setup the project env:**
- Copy and rename `.env.example` file into `.env`.
- Update the variables for database connection:
```json
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_DATABASE=<YOUR DATABSE NAME>
    DB_USERNAME=<YOUR DATABSE USERNAME>
    DB_PASSWORD=<YOUR DATABSE PASSWORD>
```

7. **Install project's dependencies**:
- Open terminal in `Visual Studio Code`.
- Run command to install dependencies
```bash
composer install
```
```bash
npm install
```

8. **Generate new application key**:
```bash
php artisan key:generate
```

9. **Migrate database**:
```bash
php artisan migrate --seed
```

10. **Migrate passport** for generate auth token:
```bash
php artisan passport:install
```

### Running
To run the project, follow these steps:

1. Open the backend using `Visual Studio Code` and termimal.

2. Start the backend serve:

```bash
php artisan serve
```

### Testing
The project supports testing with [Codeception](https://codeception.com/) and pre-commit test with [Husky](https://typicode.github.io/husky/).
- Build tester for testing:
```bash
php codecept.phar build
```
- Run Unit test in folder `/tests/Unit`
```bash
php codecept.phar run unit
```
- Run Unit test in folder `/tests/api`
```bash
php codecept.phar run api
```
- Pre-commit test is configured at `package.json`
```json
    "husky": {
        "hooks": {
            "pre-commit": "php codecept.phar run unit,api"
        }
    }
```