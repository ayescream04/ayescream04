-- Reference schema INFERRED from the queries in the PHP files.
-- Skip this if your sol_system_db already exists and matches.
CREATE DATABASE IF NOT EXISTS sol_system_db CHARACTER SET utf8mb4;
USE sol_system_db;

CREATE TABLE organizations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  org_name VARCHAR(150) NOT NULL,
  acronym VARCHAR(20) NOT NULL,
  adviser VARCHAR(120) NOT NULL,
  president VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  contact_no VARCHAR(30) NOT NULL,
  logo VARCHAR(255) NULL DEFAULT NULL,         -- file name only; file lives in assets/uploads/org_logos/
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,              -- password_hash() output
  role ENUM('admin','org') NOT NULL,
  org_id INT NULL,
  name VARCHAR(150) NOT NULL,
  FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE solicitation_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  control_no VARCHAR(30) NOT NULL UNIQUE,
  org_id INT NOT NULL,
  event_name VARCHAR(200) NOT NULL,
  purpose TEXT NOT NULL,
  target_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  datereq DATE NOT NULL,
  date_needed DATE NOT NULL,
  status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
  admin_remarks TEXT NULL,
  approved_by VARCHAR(150) NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE solicitation_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  student_name VARCHAR(150) NOT NULL,
  student_id VARCHAR(30) NULL,
  papers_returned ENUM('yes','no') NOT NULL DEFAULT 'no',
  date_returned DATE NULL,
  amount_collected DECIMAL(12,2) NOT NULL DEFAULT 0,
  receipt_no VARCHAR(50) NULL,
  remarks TEXT NULL,
  audit_status ENUM('pending_audit','audited','discrepancy') NOT NULL DEFAULT 'pending_audit',
  FOREIGN KEY (request_id) REFERENCES solicitation_requests(id) ON DELETE CASCADE
);

CREATE TABLE funds_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  org_id INT NOT NULL,
  request_id INT NOT NULL UNIQUE,
  audited_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  audit_status ENUM('audited','pending','discrepancy') NOT NULL DEFAULT 'pending',
  audit_notes TEXT NULL,
  audited_by VARCHAR(150) NOT NULL,
  audited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (request_id) REFERENCES solicitation_requests(id) ON DELETE CASCADE
);

CREATE TABLE org_directory (       -- officers and members of an organization
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50) NOT NULL,
  org_id INT NOT NULL,
  `type` ENUM('officer','member') NOT NULL DEFAULT 'member',
  `position` VARCHAR(100) NULL,    -- Chairman, Vice Chairman... (officers only)
  full_name VARCHAR(150) NOT NULL,
  department VARCHAR(100) NOT NULL,
  year_section VARCHAR(30) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE org_members (         -- members listed on the Org Profile page (created by org/profile.php)
  id INT AUTO_INCREMENT PRIMARY KEY,
  org_id VARCHAR(50) NOT NULL,
  student_id VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  department VARCHAR(50) NOT NULL DEFAULT '',
  year_section VARCHAR(50) NOT NULL DEFAULT '',
  position VARCHAR(100) NOT NULL DEFAULT 'Member',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_org_members_org (org_id)
) CHARACTER SET utf8mb4;

CREATE TABLE notifications (       -- approve / deny updates shown to the organization
  id INT AUTO_INCREMENT PRIMARY KEY,
  org_id INT NOT NULL,
  request_id INT NULL,
  type ENUM('approved','denied') NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_org_read (org_id, is_read),
  FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------
-- ALREADY HAVE THE DATABASE? Do NOT re-run the CREATE TABLE statements above.
-- Run only these on your existing sol_system_db (each is safe to skip if it
-- already exists; the PHP pages also create them automatically):
--
--   ALTER TABLE organizations DROP COLUMN department;
--   ALTER TABLE organizations ADD COLUMN logo VARCHAR(255) NULL DEFAULT NULL AFTER contact_no;
--
--   CREATE TABLE IF NOT EXISTS notifications ( ...same definition as above... );
-- ---------------------------------------------------------------------------

-- First admin: generate a hash with  php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"
-- INSERT INTO users (username, password, role, org_id, name)
-- VALUES ('admin', '<paste hash here>', 'admin', NULL, 'Office of Student Affairs');