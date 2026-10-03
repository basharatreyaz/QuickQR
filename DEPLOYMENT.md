# QuickQR SaaS - Production Deployment Guide

This guide walks you through deploying **QuickQR SaaS** into a high-performance, secure production environment (e.g., Ubuntu 22.04/24.04 LTS, Debian 12, AWS EC2, DigitalOcean Droplet, Linode, or Docker).

---

## 1. System Requirements

- **Operating System:** Linux (Ubuntu 22.04/24.04 LTS or Debian 12 recommended)
- **Web Server:** Nginx (recommended) or Apache 2.4+
- **PHP Version:** PHP 8.2 or 8.4+
- **PHP Extensions:**
  - `php-pdo` & `php-mysql`
  - `php-gd` (for QR image generation and logo embedding)
  - `php-mbstring`
  - `php-curl` (for PayPal API integrations)
  - `php-redis` (optional, for Redis high-throughput caching)
  - `php-xml`
- **Database:** MySQL 8.0+ or MariaDB 10.5+
- **In-Memory Cache (Optional but recommended):** Redis 6.0+

---

## 2. Server Setup & Dependencies (Ubuntu/Debian)

```bash
# Update package repositories
sudo apt update && sudo apt upgrade -y

# Install Nginx, MySQL, Redis, Git, Unzip
sudo apt install -y nginx mysql-server redis-server git unzip curl

# Install PHP 8.4 and required extensions
sudo apt install -y php8.4 php8.4-fpm php8.4-mysql php8.4-gd php8.4-mbstring \
                    php8.4-curl php8.4-xml php8.4-zip php8.4-redis
```

---

## 3. Clone Repository & Directory Permissions

```bash
# Clone or copy application into web root
sudo mkdir -p /var/www/quickqr
sudo chown -R $USER:www-data /var/www/quickqr
cd /var/www/quickqr

# If using git:
# git clone <repo-url> .

# Set proper directory and file permissions
sudo chown -R www-data:www-data /var/www/quickqr
sudo chmod -R 755 /var/www/quickqr

# Storage directory MUST be writable by www-data
sudo chmod -R 775 /var/www/quickqr/storage
sudo chown -R www-data:www-data /var/www/quickqr/storage
```

---

## 4. Environment Configuration

```bash
# Copy production environment template
cp .env.production.example .env

# Edit .env with your production credentials
nano .env
```

Ensure the following variables are configured:
- `APP_ENV=production`
- `APP_URL=https://qr.yourdomain.com`
- `APP_DEBUG=false`
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- `SESSION_SECRET` (generate a unique 64-char string: `openssl rand -hex 32`)
- `CACHE_DRIVER=redis` (or `file`)

---

## 5. Database Initialization

```bash
# Create production database and user in MySQL
sudo mysql -u root -p

mysql> CREATE DATABASE quickqr_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
mysql> CREATE USER 'quickqr_app'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
mysql> GRANT ALL PRIVILEGES ON quickqr_production.* TO 'quickqr_app'@'localhost';
mysql> FLUSH PRIVILEGES;
mysql> EXIT;

# Import initial schema and seeds
mysql -u quickqr_app -p quickqr_production < database/schema.sql
mysql -u quickqr_app -p quickqr_production < database/seed.sql
```

> **Security Note:** Immediately change the default admin password (`admin@quickqr.io` / `Admin@123456`) upon first login at `/admin.html`.

---

## 6. Web Server Configuration

### Option A: Nginx (Recommended)

1. Copy the provided template:
   ```bash
   sudo cp nginx.conf.example /etc/nginx/sites-available/quickqr.conf
   ```
2. Replace `qr.yourdomain.com` with your actual domain name and verify the PHP-FPM socket path.
3. Enable the site and reload Nginx:
   ```bash
   sudo ln -s /etc/nginx/sites-available/quickqr.conf /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl reload nginx
   ```

### Option B: Apache 2.4+

Ensure `mod_rewrite`, `mod_headers`, and `mod_ssl` are enabled:
```bash
sudo a2enmod rewrite headers ssl
sudo systemctl restart apache2
```
The root `.htaccess` is already hardened for Apache 2.4 with directory deny blocks and security headers.

---

## 7. SSL Certificate Installation (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d qr.yourdomain.com
```

Certbot automatically configures HTTPS renewal cron jobs and updates your Nginx configuration.

---

## 8. Scheduled Maintenance (Cron Job)

The application includes a maintenance script (`scripts/cron-cleanup.php`) that removes expired password reset tokens, purges stale cache items, and marks expired campaigns.

Add it to the `www-data` crontab:
```bash
sudo crontab -u www-data -e
```

Add the following entry (runs nightly at 3:00 AM):
```cron
0 3 * * * /usr/bin/php /var/www/quickqr/scripts/cron-cleanup.php >> /var/www/quickqr/storage/logs/cron.log 2>&1
```

---

## 9. Verification & Health Monitoring

Verify the production deployment by pinging the built-in health check endpoint:
```bash
curl -i https://qr.yourdomain.com/api/health.php
```

Expected HTTP 200 response:
```json
{
  "status": "healthy",
  "timestamp": "2026-10-03T10:00:00+00:00",
  "environment": "production",
  "php_version": "8.4.x",
  "response_ms": 12.4,
  "checks": {
    "database": true,
    "cache": true,
    "storage": true
  }
}
```

You can point external uptime monitors (e.g. UptimeRobot, Pingdom, AWS Route53 Health Checks, BetterStack) to `https://qr.yourdomain.com/api/health.php`.
