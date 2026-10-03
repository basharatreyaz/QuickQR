-- Dynamic QR Generator SaaS Seed Data

INSERT INTO plans (id, name, slug, description, price_monthly, price_yearly, qr_limit, scan_limit, allow_dynamic, allow_custom_colors, allow_logo, allow_svg, allow_advanced_analytics, allow_team, allow_api, is_active)
VALUES
(1, 'Free', 'free', 'Ideal for individuals starting with basic QR needs.', 0.00, 0.00, 5, 1000, 1, 1, 0, 0, 0, 0, 0, 1),
(2, 'Pro', 'pro', 'For creators and growing businesses who need branding and customisation.', 15.00, 144.00, 100, 50000, 1, 1, 1, 1, 1, 0, 0, 1),
(3, 'Business', 'business', 'Enterprise power with unlimited codes, SVG downloads, and full API access.', 49.00, 470.00, 999999, 1000000, 1, 1, 1, 1, 1, 1, 1, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO users (id, name, email, password_hash, role, status, plan_id, created_at)
VALUES
(1, 'System Administrator', 'admin@example.com', '$2y$12$eBuILkXeY84prO.TLnGVZeCcj0Ad/7uB7w2RF1n0AUz37r/ekfEUS', 'admin', 'active', 3, NOW()),
(2, 'Alex Morgan', 'demo@example.com', '$2y$12$eBuILkXeY84prO.TLnGVZeCcj0Ad/7uB7w2RF1n0AUz37r/ekfEUS', 'user', 'active', 2, NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO subscriptions (id, user_id, plan_id, status, billing_cycle, starts_at, ends_at)
VALUES
(1, 1, 3, 'active', 'lifetime', NOW(), DATE_ADD(NOW(), INTERVAL 10 YEAR)),
(2, 2, 2, 'active', 'yearly', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO settings (setting_key, setting_value)
VALUES
('app_name', 'QuickQR SaaS'),
('app_url', 'http://localhost/sass'),
('allow_registration', '1'),
('default_plan_id', '1'),
('rate_limit_auth_max', '10'),
('rate_limit_auth_decay', '60'),
('rate_limit_redirect_max', '120'),
('rate_limit_redirect_decay', '60'),
('enable_qr_logos', '1'),
('enable_svg_download', '1'),
('enable_billing', '1'),
('paypal_enabled', '0')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO qr_codes (id, user_id, name, short_code, type, destination, type_data, config, status, scan_count, created_at)
VALUES
(1, 2, 'Summer Marketing Campaign', 'smr2026k', 'url', 'https://example.com/summer-promo', JSON_OBJECT('url', 'https://example.com/summer-promo'), JSON_OBJECT('fg_color', '#1e293b', 'bg_color', '#ffffff', 'size', 300, 'margin', 2, 'ecc', 'M'), 'active', 6, NOW()),
(2, 2, 'Customer Feedback Form', 'feedbk9x', 'url', 'https://example.com/survey', JSON_OBJECT('url', 'https://example.com/survey'), JSON_OBJECT('fg_color', '#4338ca', 'bg_color', '#f8fafc', 'size', 300, 'margin', 2, 'ecc', 'Q'), 'active', 2, NOW()),
(3, 2, 'Headquarters Wi-Fi', 'wifi99hq', 'wifi', 'WIFI:S:Guest-Office;T:WPA;P:Welcome2026!;;', JSON_OBJECT('ssid', 'Guest-Office', 'encryption', 'WPA', 'password', 'Welcome2026!'), JSON_OBJECT('fg_color', '#0f766e', 'bg_color', '#ffffff', 'size', 300, 'margin', 2, 'ecc', 'M'), 'active', 0, NOW()),
(4, 2, 'Founder vCard Contact', 'vcard01m', 'vcard', 'BEGIN:VCARD\nVERSION:3.0\nN:Morgan;Alex;;;\nFN:Alex Morgan\nORG:TechWave Inc\nTEL;TYPE=WORK,VOICE:+14155552671\nEMAIL:alex@techwave.io\nURL:https://techwave.io\nEND:VCARD', JSON_OBJECT('first_name', 'Alex', 'last_name', 'Morgan', 'company', 'TechWave Inc', 'email', 'alex@techwave.io', 'phone', '+14155552671', 'website', 'https://techwave.io'), JSON_OBJECT('fg_color', '#0284c7', 'bg_color', '#ffffff', 'size', 300, 'margin', 2, 'ecc', 'H'), 'active', 0, NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Sample Scans for Analytics Visualization
INSERT INTO qr_scans (qr_code_id, scanned_at, ip_hash, country, country_code, region, city, device_type, os, browser, referrer, user_agent)
VALUES
(1, DATE_SUB(NOW(), INTERVAL 5 HOUR), SHA2('1.1.1.1', 256), 'United States', 'US', 'California', 'San Francisco', 'Mobile', 'iOS', 'Safari', 'https://instagram.com', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15'),
(1, DATE_SUB(NOW(), INTERVAL 4 HOUR), SHA2('1.1.1.2', 256), 'United States', 'US', 'New York', 'New York', 'Mobile', 'Android', 'Chrome Mobile', 'https://facebook.com', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36'),
(1, DATE_SUB(NOW(), INTERVAL 3 HOUR), SHA2('1.1.1.3', 256), 'United Kingdom', 'GB', 'England', 'London', 'Desktop', 'Windows', 'Chrome', 'Direct', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(1, DATE_SUB(NOW(), INTERVAL 2 HOUR), SHA2('1.1.1.4', 256), 'Germany', 'DE', 'Bavaria', 'Munich', 'Mobile', 'iOS', 'Safari', 'https://twitter.com', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15'),
(1, DATE_SUB(NOW(), INTERVAL 1 HOUR), SHA2('1.1.1.5', 256), 'India', 'IN', 'Maharashtra', 'Mumbai', 'Mobile', 'Android', 'Chrome Mobile', 'Direct', 'Mozilla/5.0 (Linux; Android 13; SM-A536B) AppleWebKit/537.36'),
(1, NOW(), SHA2('1.1.1.6', 256), 'Canada', 'CA', 'Ontario', 'Toronto', 'Tablet', 'iPadOS', 'Safari', 'https://linkedin.com', 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15'),
(2, DATE_SUB(NOW(), INTERVAL 6 HOUR), SHA2('1.1.1.7', 256), 'United States', 'US', 'Texas', 'Austin', 'Desktop', 'macOS', 'Safari', 'Direct', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15'),
(2, DATE_SUB(NOW(), INTERVAL 3 HOUR), SHA2('1.1.1.8', 256), 'France', 'FR', 'Ile-de-France', 'Paris', 'Mobile', 'Android', 'Chrome Mobile', 'https://google.com', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36');
