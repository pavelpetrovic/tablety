CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(80) PRIMARY KEY,
  value LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher VARCHAR(200) NOT NULL,
  tablets INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  removal_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_ip VARCHAR(45) NOT NULL,
  reminder_sent_at DATETIME NULL,
  INDEX idx_intervals (starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO settings (name,value) VALUES
 ('tablet_count','25'),
 ('welcome','Tablety sú uložené v uzamykateľnej skrinke. Kľúč si vyzdvihnite v kancelárii. Po použití ich, prosím, vráťte na nabíjanie.'),
 ('admin_hash',''),('teacher_hash',''),('view_token_hash',''),('view_token_plain','');
