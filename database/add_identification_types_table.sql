-- Create identification_types table
CREATE TABLE IF NOT EXISTS identification_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    country VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default identification types
INSERT INTO identification_types (code, name, country, sort_order) VALUES
('QID', 'Qatar National ID', 'Qatar', 1),
('UAE_ID', 'UAE National ID', 'United Arab Emirates', 2),
('KSA_ID', 'KSA National ID', 'Saudi Arabia', 3),
('BHR_ID', 'Bahrain National ID', 'Bahrain', 4),
('OMN_ID', 'Oman National ID', 'Oman', 5),
('KWT_ID', 'Kuwait National ID', 'Kuwait', 6),
('EGY_ID', 'Egypt National ID', 'Egypt', 7),
('JOR_ID', 'Jordan National ID', 'Jordan', 8),
('LBN_ID', 'Lebanon National ID', 'Lebanon', 9),
('PASSPORT', 'Passport', 'International', 10),
('VISA', 'Visa ID / Residence Permit', 'International', 11),
('DRIVING_LICENSE', 'Driving License', 'International', 12),
('OTHER_ID', 'Other National ID', 'International', 13);
