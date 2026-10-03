# Dynamic QR Generator SaaS - Developer Documentation

## Overview
A lightweight, modern, framework-free SaaS platform for creating, customizing, managing, and tracking dynamic QR codes with real-time analytics.

---

## Architecture

### 1. Backend Layer (`app/`)
- **`App\Config\Config`**: Environment variable loader (`.env`), environment detection (`local` vs `production`), base URL detection.
- **`App\Database\Database`**: Secure PDO singleton instance with prepared statements, error handling, and transaction support.
- **`App\Helpers`**:
  - `Response`: Standardized JSON API response format:
    - Success: `{"success": true, "data": ...}`
    - Error: `{"success": false, "error": {"code": "...", "message": "..."}}`
  - `Request`: Safe request input reader (JSON payload, POST, GET, headers, IP address, user agent).
  - `Security`: Password hashing (`password_hash` with bcrypt), password verification, cryptographic non-sequential short code generation, IP hashing with salt, destination URL validation (blocks `javascript:`, `data:`, `file:`, `vbscript:`).
  - `Validator`: Data validation rules (`required`, `email`, `url`, `min`, `max`, `in`, `numeric`).
- **`App\Middleware`**:
  - `AuthMiddleware`: Multi-mode authentication supporting PHP sessions (`$_SESSION['user_id']`) and Bearer Token / API keys (`Authorization: Bearer <key>` or `X-API-Key`).
  - `RateLimiter`: Database-backed rate limiter protecting login, registration, and redirect endpoints.
- **`App\Models`**: `User`, `Plan`, `Subscription`, `QrCode`, `QrScan`, `Setting`, `AuditLog`.
- **`App\QR`**:
  - `QrGeneratorInterface`: Pluggable QR rendering interface.
  - `QrGenerator`: Implements SVG and PNG generation with custom colors, margins, error correction (L/M/Q/H), styling (square/rounded/dots), and center logo embedding.
  - `QrContentFormatter`: Standardizes content formats for URL, Wi-Fi (`WIFI:S:...`), vCard 3.0, email (`mailto:`), phone (`tel:`), SMS (`smsto:`), location (`geo:`), and social handles.
- **`App\Services`**:
  - `AuthService`: Registration, login, profile updates, password changes.
  - `QrService`: QR lifecycle, plan limit enforcement, destination validation, duplication, soft deletion, and download handlers.
  - `AnalyticsService`: User-Agent parser (device type, operating system, browser), privacy-preserving IP hashing, geo estimation, scan event recording.

---

## Dynamic Redirection Flow

```text
User Scans QR Code
       │
       ▼
https://domain.com/r/{short_code}
       │
       ▼
redirect/index.php
       │
       ├── 1. Rate limiter check (DDoS prevention)
       ├── 2. Query QR Code by short_code
       ├── 3. Status verification:
       │      ├─ 'active'  ──► Records scan & redirects (302) to customer destination
       │      ├─ 'paused'  ──► Displays branded "Campaign Paused" page
       │      ├─ 'expired' ──► Displays branded "Campaign Expired" page
       │      └─ 'deleted' / not found ──► Displays branded "Not Found" 404 page
       │
       └── 4. Analytics recorded asynchronously/safely:
              - IP hash (SHA-256 with salt)
              - Device type (Mobile, Desktop, Tablet)
              - Operating System & Browser
              - Country & Location
              - Referrer
```

---

## API Endpoints Reference

### Authentication
- `POST /api/auth/register.php`: Create account
- `POST /api/auth/login.php`: Authenticate and start session
- `POST /api/auth/logout.php`: Destroy session
- `GET  /api/auth/me.php`: Current user profile, active plan, and usage stats
- `POST /api/auth/reset-password.php`: Password recovery endpoint

### QR Codes
- `GET  /api/qr/list.php`: List user QR codes (with pagination, search, status filters)
- `POST /api/qr/create.php`: Create dynamic QR code
- `GET  /api/qr/get.php?id={id}`: Fetch single QR code details
- `POST /api/qr/update.php`: Update target URL, label, styling, or expiration
- `POST /api/qr/delete.php`: Soft delete QR code
- `POST /api/qr/duplicate.php`: Duplicate existing QR code
- `POST /api/qr/toggle-status.php`: Toggle active / paused status
- `GET  /api/qr/preview.php`: Real-time SVG/PNG preview generator
- `GET  /api/qr/download.php?id={id}&format={png|svg}`: Download QR file
- `POST /api/qr/upload-logo.php`: Upload custom center logo

### Analytics
- `GET  /api/analytics/stats.php?qr_id={id}&days=30`: Detailed metrics, timeline, and breakdown charts for a QR code
- `GET  /api/analytics/stats.php`: Overview summary across all user QR codes

### User & Billing
- `GET  /api/user/profile.php`: Retrieve user profile
- `POST /api/user/profile.php`: Update name/email or regenerate API key
- `POST /api/user/change-password.php`: Update password
- `GET  /api/user/plans.php`: List active subscription plans
- `POST /api/user/plans.php`: Subscribe or upgrade tier

### Admin Console
- `GET  /api/admin/stats.php`: Platform-wide stats and audit log stream
- `GET  /api/admin/users.php`: Manage users with pagination and search
- `POST /api/admin/user-status.php`: Enable/disable user accounts, change roles/plans
- `GET  /api/admin/qrs.php`: View all platform QR campaigns
- `GET  /api/admin/plans.php`: List configurable plans
- `POST /api/admin/plans.php`: Update plan pricing, QR limits, scan limits
- `GET  /api/admin/settings.php`: Read system settings
- `POST /api/admin/settings.php`: Save system settings

---

## Default Accounts (Seed Data)

| Role | Email | Password |
|---|---|---|
| **System Administrator** | `admin@example.com` | `admin123` |
| **Demo User** | `demo@example.com` | `admin123` |

---

## Automated Verification

Run the test suite anytime:
```bash
php tests/test_suite.php
```
