CREATE TABLE IF NOT EXISTS proposal_letters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT NOT NULL,
  letter_number VARCHAR(50) NULL,
  template_type VARCHAR(80) NOT NULL DEFAULT 'Residential Construction Proposal',
  subject VARCHAR(255) NULL,
  body LONGTEXT NOT NULL,
  paper_size VARCHAR(30) NOT NULL DEFAULT 'A4',
  prepared_by VARCHAR(150) NULL,
  approved_by VARCHAR(150) NULL,
  status ENUM('draft','final','submitted','approved','rejected') DEFAULT 'draft',
  created_by INT NULL,
  updated_by INT NULL,
  printed_at DATETIME NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_proposal_letters_proposal_id (proposal_id),
  INDEX idx_proposal_letters_status (status),
  INDEX idx_proposal_letters_updated_at (updated_at)
);

CREATE TABLE IF NOT EXISTS proposal_letter_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT NOT NULL,
  header_mode VARCHAR(20) NOT NULL DEFAULT 'text',
  header_title VARCHAR(180) NULL,
  header_subtitle VARCHAR(180) NULL,
  header_line1 VARCHAR(255) NULL,
  header_line2 VARCHAR(255) NULL,
  header_image_path VARCHAR(255) NULL,
  show_header TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_proposal_letter_settings_proposal (proposal_id)
);
