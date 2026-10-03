<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\Models\Setting;

Database::execute("
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    order_id VARCHAR(100) NULL,
    transaction_id VARCHAR(100) NULL,
    payment_method VARCHAR(50) DEFAULT 'paypal',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    payer_email VARCHAR(255) NULL,
    metadata TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Initialize default paypal settings if not set
if (Setting::get('paypal_enabled') === null) {
    Setting::set('paypal_enabled', '0');
}
if (Setting::get('paypal_mode') === null) {
    Setting::set('paypal_mode', 'sandbox');
}
if (Setting::get('paypal_currency') === null) {
    Setting::set('paypal_currency', 'USD');
}
if (Setting::get('paypal_client_id') === null) {
    Setting::set('paypal_client_id', '');
}
if (Setting::get('paypal_secret') === null) {
    Setting::set('paypal_secret', '');
}

echo "Payments table and default PayPal settings initialized successfully!\n";
