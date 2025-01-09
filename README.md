# 🕒 On-Call Duty Planner 📋

## 🌟 Overview
On-Call Duty Planner is a web-based application designed to manage and schedule on-call shifts for database teams. It supports multiple teams, shift types, and role-based access control.

![Dashboard Screenshot](/screenshots/Dashboard.png)

## ✨ Features
- 🏢 Multiple Teams Support: Hana, Oracle, Postgres, SQLServer
- 👥 User Management
- 🔐 Role-Based Access Control
- 📅 Shift Scheduling
- ✅ Shift Approval Workflow

## 🛠 Tech Stack
- 🐘 PHP
- 🗃 MySQL
- 🌐 HTML5
- 🎨 CSS (Bootstrap, Tailwind)
- ⚡ JavaScript
- 📧 PHPMailer (for email notifications)

## 📋 Prerequisites
- 🔷 PHP 7.4+
- 🐬 MySQL 5.7+
- 📦 Composer (for dependency management)
- 🌐 Web Server (Apache/Nginx)

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/dutyplanner.git
cd dutyplanner
```

### 2. Configure Database
1. Create a MySQL database
2. Copy `includes/config.example.php` to `includes/config.php`
3. Edit `config.php` with your database credentials

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'dutyplanner');
```

### 3. Setup Database
Run the database setup script:
```bash
mysql -u your_username -p dutyplanner < database.sql
```

### 4. Configure Email (Optional)
Edit email settings in `config.php`:
```php
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', '587');
define('SMTP_USER', 'your_email');
define('SMTP_PASS', 'your_password');
```

### 5. Install Dependencies
```bash
composer install
```

### 6. Web Server Configuration
Configure your web server to point to the project root directory.

## 🔒 Security
- 🚫 Never commit `config.php` to version control
- 🔐 Use strong, unique passwords
- 🆙 Keep dependencies updated

## 🔑 Default Credentials
- 👤 Username: admin
- 🔐 Password: admin123

## 🛠 Troubleshooting
- 📜 Check error logs in `logs/` directory
- ✅ Ensure all PHP extensions are installed
- 🔍 Verify database connection settings

## 🚧 Development Roadmap
- [ ] 🗑 Implement shift deletion functionality
- [ ] 🔄 Implement shift change functionality
- [ ] 📤 Implement shift export functionality
- [ ] 📊 Add more advanced reporting features
- [ ] 📱 Enhance mobile responsiveness
- [ ] 📆 Integrate with external calendar services

## 🤝 Contributing
1. 🍴 Fork the repository
2. 🌿 Create a feature branch
3. 🔨 Commit your changes
4. 📤 Push to the branch
5. 🔀 Create a Pull Request

## 🤳 Screenshots
![Approve Shifts Screenshot](/screenshots/ApproveShifts.png)
![Create My Shift Screenshot](/screenshots/CreateMyShift.png)
![My Shifts Screenshot](/screenshots/MyShifts.png)
![Shift Reports Screenshot](/screenshots/ShiftReports.png)
![Team Management Screenshot](/screenshots/TeamManagement.png)
![User Management Screenshot](/screenshots/UserManagement.png)

## 📄 License
MIT License

Copyright (c) 2024 On-Call Duty Planner

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## 📧 Contact
[Your contact information]# DutyPlanner
