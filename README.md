# Full-Stack Webinar Registration System
> Production-ready event registration platform for **MSME CONNECT Summit 2026** — Business Growth Workshop through Cash Flow Management.
[![Live Demo](https://img.shields.io/badge/Live_Demo-workshop.indiansmechamber.com-2563eb?style=for-the-badge)](https://workshop.indiansmechamber.com/)
[![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-Private-lightgrey?style=flat-square)](#)
**Live site:** [https://workshop.indiansmechamber.com/](https://workshop.indiansmechamber.com/)
A full-stack webinar/workshop registration and content management system built for [CIMSME](https://www.indiansmechamber.com/) (Chamber of Indian Micro Small and Medium Enterprises). It powers a responsive landing page, OTP-based user authentication, participant dashboard, and a secure admin panel for managing registrations, payments, and dynamic event content.
---
## Table of Contents
- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Screenshots](#screenshots)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [Database Setup](#database-setup)
- [Admin Access](#admin-access)
- [API Endpoints](#api-endpoints)
- [Security](#security)
- [Deployment Notes](#deployment-notes)
- [Author](#author)
---
## Overview
This platform was built to handle real-world event registration at scale for the **MSME CONNECT Summit 2026** — a live business growth workshop hosted by **Shri Mukesh Mohan Gupta**, President of CIMSME.
The system covers the full participant journey:
1. **Discover** — Responsive landing page with dynamic hero, seat urgency, policy advocacy, and event details  
2. **Register** — Multi-step form with mobile OTP verification  
3. **Pay** — Configurable registration fee and external payment link  
4. **Login** — OTP-based access to personal dashboard  
5. **Attend** — Workshop link delivered after verification and payment  
Admins manage everything from a dedicated backend: submissions, payment status, hero content, seat banners, policy cards, and CSV exports.
---
## Key Features
### Public Website
- Modern, mobile-first landing page with smooth navigation and accessibility support
- Dynamic hero section (badge, title, guest info, meta grid, highlights) loaded via API
- Real-time **seats urgency** banner with progress indicator
- Policy advocacy section with CMS-managed cards
- Save-the-date section with editable details
- Registration page with Indian mobile validation, pincode/state/district capture
- **SMS OTP verification** for registration and login
- User dashboard with payment status and workshop access link
### Admin Panel (`/admin/`)
- Secure admin login with CSRF protection and brute-force lockout
- Dashboard with registration stats and quick actions
- View, filter, and manage all submissions
- Update **payment status** and **verification status** per registrant
- Configure registration fee, payment link, and workshop URL
- CMS for hero section, seats urgency, policy advocacy, and save-the-date
- CSV export and file download for registrant uploads
- SMS test utility for OTP delivery debugging
### Backend & Architecture
- PHP 8+ with strict typing and repository pattern
- REST-style JSON API endpoints
- MySQL with normalized schema and foreign keys
- Centralized validation, security headers, and rate limiting
- Environment-based configuration via `.env`
- Structured logging to `storage/logs/`
---
## Tech Stack
| Layer | Technologies |
|-------|-------------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Backend** | PHP 8+, PDO |
| **Database** | MySQL 8+ / MariaDB |
| **Auth** | Session-based admin auth, SMS OTP for users |
| **SMS** | Bulk SMS gateway (ConnectBind-compatible API) |
| **Server** | Apache with `.htaccess` (mod_rewrite) |
---
## Screenshots
> Add screenshots to your repo (e.g. `docs/screenshots/`) and update the paths below.
| Landing Page | Registration |
|:---:|:---:|
| ![Landing Page](docs/screenshots/landing.png) | ![Registration](docs/screenshots/register.png) |
| User Dashboard | Admin Panel |
|:---:|:---:|
| ![Dashboard](docs/screenshots/dashboard.png) | ![Admin](docs/screenshots/admin.png) |
---
## Project Structure
├── index.html # Main landing page ├── register.html # Registration form (OTP flow) ├── login.php # User OTP login ├── dashboard.php # Participant dashboard ├── api/ # JSON API endpoints ├── admin/ # Admin panel (CMS + submissions) ├── includes/ # Core PHP (repos, auth, OTP, security) ├── config/ # App & database configuration ├── database/ # SQL schema & utility scripts ├── assets/ # Admin & login JavaScript/CSS ├── images/ # Static media assets ├── uploads/ # User/admin uploaded files └── storage/ # Logs & install lock

---
## Getting Started
### Prerequisites
- PHP **8.0+** with extensions: `pdo_mysql`, `mbstring`, `json`, `openssl`
- MySQL **8.0+** or MariaDB **10.4+**
- Apache (or Nginx + PHP-FPM) with document root pointing to project folder
- Composer **not required** — zero-dependency PHP architecture
### Installation
1. **Clone the repository**
   ```bash
   git clone https://github.com/sudhanshu140101/fullstack-webinar-registration-system.git
   cd fullstack-webinar-registration-system
Create environment file

cp .env.example .env
Edit .env with your database and SMS credentials (see Environment Variables).

Import the database

mysql -u root -p msme_connect < database/schema.sql
Set directory permissions (Linux/macOS)

chmod -R 750 storage uploads
Create admin user (recommended — do not use default schema credentials in production)

php database/seed_admin.php admin admin@example.com "YourSecurePassword123!" "Admin Name"
Open in browser

Public site: http://localhost/
Admin panel: http://localhost/admin/
Environment Variables
Create a .env file in the project root (never commit this file):

# Application
APP_NAME="MSME Connect Summit"
APP_URL=https://workshop.indiansmechamber.com
APP_ENV=production
APP_DEBUG=false
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=msme_connect
DB_USER=your_db_user
DB_PASS=your_db_password
# Session
SESSION_NAME=MSME_CONNECT_SESSID
SESSION_LIFETIME=3600
SESSION_SECURE=true
SESSION_SAMESITE=Lax
# Security
LOGIN_MAX_ATTEMPTS=20
LOGIN_LOCKOUT_MINUTES=30
RATE_LIMIT_SUBMISSIONS=20
# OTP
OTP_VALIDITY_MINUTES=5
OTP_MAX_SEND_PER_HOUR=5
OTP_RESEND_COOLDOWN_SECONDS=120
OTP_MAX_VERIFY_ATTEMPTS=5
OTP_HMAC_SECRET=change-this-to-a-long-random-secret
OTP_LOG_FOR_TESTING=false
# SMS Gateway
SMS_ENABLED=true
SMS_API_URL=https://your-sms-provider.example/bulksms
SMS_USERNAME=your_username
SMS_PASSWORD=your_password
SMS_SENDER_ID=MSMEAP
SMS_ENTITY_ID=
SMS_TEMPLATE_ID=
SMS_TMID=
SMS_OTP_MESSAGE="{otp} is your OTP to verify your number. Do not share it with anyone."
SMS_DESTINATION_PREFIX=91
# Uploads
UPLOAD_MAX_SIZE=5242880
Database Setup
The full schema is in database/schema.sql. It includes tables for:

Table	Purpose
registrations
Participant registration records
registration_files
Uploaded documents per registration
registration_settings
Fee, payment URL, workshop URL
payment_orders
Payment order tracking
hero_section / hero_meta_items / hero_highlights
Dynamic hero CMS
policy_advocacy / policy_advocacy_cards
Policy section CMS
save_the_date_section / save_the_date_details
Event date CMS
seats_urgency_banner
Live seat availability banner
admins
Admin accounts
login_attempts
Brute-force protection log
Utility scripts (CLI only):

php database/seed_admin.php          # Create/update admin user
php database/audit_production.php    # Production audit checks
Admin Access
Route	Description
/admin/login.php
Admin sign-in
/admin/dashboard.php
Overview & registration settings
/admin/submissions.php
All registrants
/admin/view.php
Single registrant detail
/admin/export.php
CSV export
/admin/hero.php
Hero section CMS
/admin/seats-urgency.php
Seat urgency banner
/admin/policy-advocacy.php
Policy cards CMS
/admin/save-the-date.php
Save-the-date CMS
Security: Always create a new admin via seed_admin.php in production. Never expose admin credentials in public repositories.

API Endpoints
Method	Endpoint	Description
GET
/api/csrf.php
Fetch CSRF token
GET
/api/hero.php
Dynamic hero content
GET
/api/seats-urgency.php
Seat urgency banner data
GET
/api/policy-advocacy.php
Policy advocacy cards
GET
/api/save-the-date.php
Save-the-date content
GET
/api/registration-settings.php
Fee & payment settings
GET
/api/recent-registrants.php
Recent registrations (public ticker)
POST
/api/submit.php
Submit registration form
POST
/api/registration-otp-send.php
Send registration OTP
POST
/api/registration-otp-verify.php
Verify registration OTP
POST
/api/login-otp-send.php
Send login OTP
POST
/api/login-otp-verify.php
Verify login OTP
All POST endpoints require a valid CSRF token.

Security
CSRF token validation on all state-changing requests
Rate limiting on registrations and OTP sends
Admin login lockout after failed attempts
Secure session configuration (HttpOnly, SameSite, optional Secure flag)
Input sanitization and server-side validation via Validator class
Security headers sent on every request
.env excluded from version control — secrets never in repo
Upload directory protected with .htaccess
Sensitive folders (config/, includes/, database/, storage/) blocked from direct web access
Deployment Notes
Production deployment: workshop.indiansmechamber.com

Recommended production checklist:


 Set APP_ENV=production and APP_DEBUG=false

 Enable SESSION_SECURE=true (HTTPS required)

 Use strong OTP_HMAC_SECRET

 Configure real SMS gateway credentials

 Run php database/seed_admin.php with a strong password

 Ensure storage/ and uploads/ are writable by the web server

 Import database/schema.sql on production MySQL

 Verify .env is not accessible from the web
Server requirements: Apache with mod_rewrite, PHP 8+, MySQL 8+.

Author
Sudhanshu

GitHub: @sudhanshu140101
Project: fullstack-webinar-registration-system
Live Demo: workshop.indiansmechamber.com
Acknowledgements
Built for CIMSME — Chamber of Indian Micro Small and Medium Enterprises
Event hosted by Shri Mukesh Mohan Gupta, President, CIMSME

MSME CONNECT Summit 2026 · Business Growth Workshop through Cash Flow Management

```
