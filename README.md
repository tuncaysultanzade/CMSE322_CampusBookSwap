# 📚 Campus BookSwap

> Campus Second-Hand Book Sales and Exchange Platform  
> **Course Project for CMSE322 - EMU**

![PHP](https://img.shields.io/badge/PHP-7.4+-blue?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-blue?logo=mysql)
![Bootstrap](https://img.shields.io/badge/Bootstrap-Responsive-blue?logo=bootstrap)

## 👥 Group Members

- **Tuncay Sultanzade**
- **Osman Ata Nurçin**
- **Anıl Türk**
- **Erbay Ataç**

## 🛠️ Technologies Used

- PHP  
- MySQL  
- jQuery & AJAX  
- Bootstrap (HTML, CSS)

## ✨ Features

- Book listings (for sale or exchange)  
- User profiles with authentication  
- Real-time messaging system  
- Favorites and ratings  
- Demo credit card checkout  
- Advanced search and filters  
- Responsive design  
- Admin moderation panel

## ⚙️ Requirements

- PHP 7.4+ (PHP 8.2 recommended)  
- MySQL 5.7+ (MariaDB recommended)  
- Apache/Nginx web server  
- `mod_rewrite` module enabled (for clean URLs)  
- GD extension enabled (for secure image uploads)  
> _(Check `php.ini`: `extension=gd`)_

## 🚀 Installation

1. **Create a MySQL database and import the schema**

Using command line:
```bash
mysql -u campusbookswap -p
CREATE DATABASE campusbookswap;
USE campusbookswap;
source campusbookswap.sql;
```

Or using XAMPP/phpMyAdmin:  
Go to [http://localhost/phpmyadmin](http://localhost/phpmyadmin), create the database manually, then copy and paste the SQL commands from `campusbookswap.sql`.

2. **Configure database connection**  
Edit the `config.php` file:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'campusbookswap');
define('DB_PASS', '134679');
define('DB_NAME', 'campusbookswap');
```

3. **Set up the uploads directory**
```bash
mkdir uploads
chmod 777 uploads
```
Make sure PHP has write permissions for this directory.

4. **Deploy the project**  
Upload the project folder to your web server root directory (e.g., `htdocs` or `/var/www/html`) and ensure permissions are set correctly.

## 🧪 Test Accounts

| Role   | Email                    | Password    |
|--------|--------------------------|-------------|
| Admin  | demoadmin@example.com    | 123456789   |
| Seller | demoseller@example.com   | 123456789   |
| Buyer  | demobuyer@example.com    | 123456789   |

## 👨‍💻 Development Notes

- Developed with core PHP (no frameworks)  
- Functional coding style  
- All user input validated  
- XSS and SQL Injection protections in place  
- Structured for clarity, performance, and security

## 🔐 Security Features

- Password hashing using `bcrypt`  
- CSRF protection  
- SQL injection prevention  
- XSS filtering  
- File upload validation  
- Admin-only access control

## 📄 License

This project is intended for academic use only.  
Feel free to modify and reuse for educational or portfolio purposes.

## 📬 Contact

For any questions or feedback, please contact the group member listed above.
