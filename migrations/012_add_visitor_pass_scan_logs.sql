-- Track visitor pass QR scans and usage lifecycle.
CREATE TABLE IF NOT EXISTS visitor_pass_scan_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_pass_id INT NOT NULL,
    homeowner_id INT NULL,
    qr_token VARCHAR(128) NULL,
    scan_status VARCHAR(32) NOT NULL,
    scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scanner_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    notes TEXT NULL,
    INDEX idx_pass (visitor_pass_id),
    INDEX idx_homeowner (homeowner_id),
    INDEX idx_scanned_at (scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
