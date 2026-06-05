-- Staff accounts only.
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
  user_id INT PRIMARY KEY,
  role VARCHAR(32) NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE staff_profiles (
  user_id INT PRIMARY KEY,
  job_title VARCHAR(120) NULL,
  department VARCHAR(120) NULL,
  phone VARCHAR(64) NULL,
  address VARCHAR(255) NULL,
  bio TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE staff_activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_id INT NULL,
  action VARCHAR(120) NOT NULL,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staff_activity_user (user_id, created_at),
  INDEX idx_staff_activity_actor (actor_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE site_settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value LONGTEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('maintenance_mode', '0'),
  ('maintenance_message', 'We are currently doing maintenance. Please check back later.'),
  ('maintenance_retry_after', '3600');

-- Client portal accounts, used only by /client/login.php.
CREATE TABLE website_clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  phone VARCHAR(64) NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE website_client_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  project_id VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_client_project (client_id, project_id),
  FOREIGN KEY (client_id) REFERENCES website_clients(id) ON DELETE CASCADE
);

-- Updated to match your spreadsheet layout
CREATE TABLE purchase_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,

  -- Header fields
  date DATE NOT NULL,
  supplier VARCHAR(255) NOT NULL,
  ref_page VARCHAR(120),
  tin VARCHAR(120),
  vat_nvat ENUM('VAT','NonVAT') DEFAULT 'VAT',
  address VARCHAR(255),
  category VARCHAR(255),
  description TEXT,
  project_name VARCHAR(255),
  reference VARCHAR(255),

  -- D  E  B  I  T
  input_vat DECIMAL(12,2) DEFAULT 0,
  vatable DECIMAL(12,2) DEFAULT 0,
  non_vat DECIMAL(12,2) DEFAULT 0,
  total DECIMAL(12,2) DEFAULT 0,
  freight_handling DECIMAL(12,2) DEFAULT 0,
  cash DECIMAL(12,2) DEFAULT 0,

  -- S  U  N  D  R  Y
  account_title VARCHAR(255),
  debit DECIMAL(12,2) DEFAULT 0,
  credit DECIMAL(12,2) DEFAULT 0,

  remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
