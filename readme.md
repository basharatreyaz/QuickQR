# Dynamic QR Generator SaaS

A modern SaaS platform for creating, managing, and tracking **dynamic QR codes**.

The application uses a lightweight frontend built with **HTML, CSS, and JavaScript**, with a **PHP backend** and database for authentication, QR management, redirects, analytics, and subscriptions.

## Features

### QR Code Generation

Users can create QR codes for different content types:

- URL
- Plain text
- Email
- Phone number
- SMS
- Wi-Fi
- vCard / contact
- Location
- Social media profiles
- Custom QR destination

### Dynamic QR Codes

Dynamic QR codes use a permanent QR code while allowing the destination to be changed later.

Example:

```text
QR Code
   ↓
https://qr.example.com/r/abc123
   ↓
Current destination
https://example.com/page
```

The QR code itself does not need to be regenerated when the destination changes.

### QR Management

Users can:

- Create QR codes
- Edit QR codes
- Delete QR codes
- Pause QR codes
- Activate QR codes
- Duplicate QR codes
- Rename QR codes
- Organise QR codes
- Search QR codes
- Filter QR codes
- Download QR codes

### Analytics

Track QR activity such as:

- Total scans
- Unique scans
- Scans over time
- Device type
- Operating system
- Browser
- Country
- Referrer
- Approximate location
- Last scanned time

Analytics should respect applicable privacy laws and avoid collecting unnecessary personal information.

### QR Customisation

Allow users to customise:

- Foreground colour
- Background colour
- QR size
- Error correction
- Logo
- Margin
- Rounded modules
- Frame
- Label
- Download format

Supported formats may include:

- PNG
- SVG
- PDF

### Authentication

Users should be able to:

- Register
- Login
- Logout
- Reset password
- Verify email
- Update profile
- Change password

### SaaS Plans

Example plans:

| Feature | Free | Pro | Business |
|---|---:|---:|---:|
| QR Codes | 5 | 100 | Unlimited |
| Dynamic QR | Yes | Yes | Yes |
| Analytics | Basic | Advanced | Advanced |
| Custom Branding | No | Yes | Yes |
| Logo | No | Yes | Yes |
| SVG Download | No | Yes | Yes |
| Team Members | No | No | Yes |
| API | No | No | Yes |

The actual limits should be configurable from the admin panel rather than hard-coded.

### Admin Panel

Administrators should be able to:

- View users
- Manage users
- Disable accounts
- View QR codes
- View scans
- Manage plans
- Configure limits
- View system statistics
- Manage subscriptions
- View audit logs
- Configure application settings

---

# Technology Stack

## Frontend

- HTML5
- CSS3
- Vanilla JavaScript
- Fetch API
- Responsive design
- No frontend framework required

The frontend should communicate with the PHP backend through REST-style API endpoints.

## Backend

- PHP 8.2+
- MySQL / MariaDB
- PDO
- REST-style API
- Session or token-based authentication

## QR Generation

Use a reliable server-side QR library where required.

Possible PHP libraries can be evaluated during implementation, but the QR generation layer should be abstracted so the library can be replaced without rewriting the application.

---

# Project Structure

```text
dynamic-qr-saas/
│
├── public/
│   ├── index.html
│   ├── login.html
│   ├── register.html
│   ├── dashboard.html
│   ├── qr-create.html
│   ├── qr-edit.html
│   ├── analytics.html
│   ├── pricing.html
│   ├── settings.html
│   ├── css/
│   │   ├── app.css
│   │   ├── dashboard.css
│   │   └── components.css
│   │
│   ├── js/
│   │   ├── app.js
│   │   ├── auth.js
│   │   ├── dashboard.js
│   │   ├── qr.js
│   │   ├── analytics.js
│   │   └── api.js
│   │
│   └── assets/
│
├── api/
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   └── reset-password.php
│   │
│   ├── qr/
│   │   ├── create.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   ├── list.php
│   │   ├── duplicate.php
│   │   └── download.php
│   │
│   ├── analytics/
│   │   └── stats.php
│   │
│   └── user/
│       ├── profile.php
│       └── settings.php
│
├── redirect/
│   └── index.php
│
├── admin/
│   ├── index.php
│   ├── users.php
│   ├── qr-codes.php
│   ├── plans.php
│   └── settings.php
│
├── app/
│   ├── Config/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Middleware/
│   ├── Database/
│   ├── Helpers/
│   └── QR/
│
├── storage/
│   ├── logs/
│   ├── qr/
│   └── cache/
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── .env.example
├── .gitignore
├── composer.json
├── ai.md
└── README.md
```

---

# Dynamic QR Architecture

A dynamic QR code should never point directly to the customer's destination.

Instead:

```text
QR
 │
 ▼
https://example.com/r/8Fh72K
 │
 ▼
PHP Redirect Controller
 │
 ├── Check QR status
 ├── Record scan
 ├── Apply analytics rules
 └── Redirect
       │
       ▼
https://customer-destination.com
```

The redirect identifier should be random and non-sequential.

Example:

```text
/r/8Fh72KxP
```

Do not expose database IDs such as:

```text
/r/123
```

---

# API

All API responses should use JSON.

Example success response:

```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "My Website",
    "short_code": "8Fh72KxP"
  }
}
```

Example error:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Destination URL is required."
  }
}
```

## Example Endpoints

### Authentication

```text
POST /api/auth/register.php
POST /api/auth/login.php
POST /api/auth/logout.php
POST /api/auth/reset-password.php
```

### QR Codes

```text
GET    /api/qr/list.php
POST   /api/qr/create.php
GET    /api/qr/get.php?id=123
POST   /api/qr/update.php
POST   /api/qr/delete.php
POST   /api/qr/duplicate.php
```

### Analytics

```text
GET /api/analytics/stats.php?qr_id=123
```

### Redirect

```text
GET /r/{short_code}
```

---

# Database

Core tables should include:

```text
users
qr_codes
qr_scans
plans
subscriptions
payments
api_keys
teams
team_members
audit_logs
settings
```

A QR record should contain information similar to:

```text
id
user_id
name
short_code
type
destination
status
created_at
updated_at
expires_at
scan_count
```

The `qr_scans` table should contain analytics information such as:

```text
id
qr_code_id
scanned_at
ip_hash
country
region
city
device_type
os
browser
referrer
```

Avoid storing raw IP addresses unless there is a documented requirement to do so.

---

# Security

Security is a core requirement.

The application must:

- Use PDO prepared statements
- Validate all input
- Escape output
- Hash passwords with `password_hash()`
- Verify passwords with `password_verify()`
- Use CSRF protection where cookie-based authentication is used
- Protect API endpoints
- Rate-limit authentication endpoints
- Rate-limit QR redirect abuse
- Validate destination URLs
- Prevent open redirect abuse
- Prevent SQL injection
- Prevent XSS
- Prevent CSRF
- Prevent IDOR
- Validate uploaded files
- Restrict file sizes
- Protect `.env`
- Disable directory listing
- Avoid exposing stack traces in production
- Log security events

Never trust frontend validation alone.

---

# Destination URL Security

Dynamic redirects introduce an open-redirect risk.

Destination URLs must be validated before being stored.

Only explicitly supported URL schemes should be accepted.

For example:

```text
https://
http://
```

Do not allow:

```text
javascript:
data:
file:
vbscript:
```

The redirect endpoint must also verify that the QR code is active and has not expired.

---

# QR Status

Possible statuses:

```text
active
paused
expired
deleted
```

Redirect behaviour:

```text
active  → redirect
paused  → show inactive page
expired → show expired page
deleted → show not-found page
```

---

# Performance

The redirect endpoint is performance-sensitive because it can receive significantly more traffic than the dashboard.

The redirect flow should:

1. Resolve the short code efficiently.
2. Check status.
3. Record analytics efficiently.
4. Redirect immediately.

Indexes should exist for:

```text
qr_codes.short_code
qr_codes.user_id
qr_scans.qr_code_id
qr_scans.scanned_at
```

Analytics processing can later be moved to a queue/background worker if traffic becomes large.

---

# Environment Configuration

Use environment variables.

Example:

```env
APP_ENV=production
APP_URL=https://example.com

DB_HOST=localhost
DB_NAME=dynamic_qr
DB_USER=root
DB_PASSWORD=

SESSION_SECRET=

MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=

STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

Never commit production secrets to Git.

---

# Local Development

Example:

```bash
git clone <repository>
cd dynamic-qr-saas

composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Create the database and import:

```text
database/schema.sql
```

Then configure the web server so that:

```text
public/
```

is the public document root.

---

# Production

Recommended environment:

- PHP 8.2+
- MySQL 8+ / MariaDB
- Nginx or Apache
- HTTPS
- PHP OPcache
- Cron
- SMTP provider
- Object storage for larger deployments

Production should use:

```text
APP_ENV=production
```

Debug output must be disabled.

---

# Future Features

Potential future features include:

- QR folders
- Bulk QR creation
- Bulk export
- QR templates
- Custom domains
- White-label QR pages
- Team collaboration
- API access
- Webhooks
- UTM parameters
- A/B destinations
- Geo-based redirects
- Device-based redirects
- Password-protected QR codes
- Expiring QR codes
- Scan notifications
- Zapier integration
- Stripe subscriptions
- Usage-based billing

---

# Development Principles

1. Keep the frontend framework-free.
2. Keep backend logic modular.
3. Never place database queries directly in presentation files.
4. Use API endpoints for frontend/backend communication.
5. Keep configuration outside source code.
6. Validate input on the server.
7. Keep authentication and authorisation separate.
8. Design the redirect system for high traffic.
9. Make SaaS limits configurable.
10. Do not build unnecessary features before the core QR workflow is stable.
```

