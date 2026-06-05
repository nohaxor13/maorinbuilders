-- Maorin Builders full workspace upgrade
-- Safe additive migration. Run in phpMyAdmin on the existing maorin_builders database.
SET FOREIGN_KEY_CHECKS=0;

INSERT IGNORE INTO roles (slug, name, is_system) VALUES
('admin','Administrator',1),('engineer_owner','Engineer / Owner',1),('assistant','Assistant',1),('encoder','Encoder',1),('staff','Staff',1),('accounting','Accounting (Legacy)',1),('warehouse','Warehouse (Legacy)',1);

CREATE TABLE IF NOT EXISTS mb_projects (
 id INT AUTO_INCREMENT PRIMARY KEY, project_code VARCHAR(64) NULL UNIQUE, name VARCHAR(180) NOT NULL, client_name VARCHAR(180) NULL, client_email VARCHAR(180) NULL, client_phone VARCHAR(64) NULL, location VARCHAR(255) NULL, project_type VARCHAR(80) NULL, status VARCHAR(32) NOT NULL DEFAULT 'proposed', progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0, start_date DATE NULL, target_end_date DATE NULL, estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0, actual_cost DECIMAL(14,2) NOT NULL DEFAULT 0, contract_amount DECIMAL(14,2) NOT NULL DEFAULT 0, notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_mb_projects_status (status), INDEX idx_mb_projects_client (client_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_project_updates (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NOT NULL, update_date DATE NOT NULL, title VARCHAR(180) NOT NULL, progress_percent TINYINT UNSIGNED NULL, cost_added DECIMAL(14,2) NOT NULL DEFAULT 0, details TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_mb_project_updates_project (project_id, update_date), FOREIGN KEY (project_id) REFERENCES mb_projects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_estimates (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NULL, estimate_no VARCHAR(64) NULL UNIQUE, title VARCHAR(180) NOT NULL, client_name VARCHAR(180) NULL, status VARCHAR(32) NOT NULL DEFAULT 'draft', labor_cost DECIMAL(14,2) NOT NULL DEFAULT 0, material_cost DECIMAL(14,2) NOT NULL DEFAULT 0, equipment_cost DECIMAL(14,2) NOT NULL DEFAULT 0, overhead_cost DECIMAL(14,2) NOT NULL DEFAULT 0, markup_percent DECIMAL(8,2) NOT NULL DEFAULT 0, tax_percent DECIMAL(8,2) NOT NULL DEFAULT 0, subtotal DECIMAL(14,2) NOT NULL DEFAULT 0, markup_amount DECIMAL(14,2) NOT NULL DEFAULT 0, tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0, grand_total DECIMAL(14,2) NOT NULL DEFAULT 0, notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_mb_estimates_project (project_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_proposals (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NULL, estimate_id INT NULL, proposal_no VARCHAR(64) NULL UNIQUE, title VARCHAR(180) NOT NULL, client_name VARCHAR(180) NULL, status VARCHAR(32) NOT NULL DEFAULT 'draft', amount DECIMAL(14,2) NOT NULL DEFAULT 0, valid_until DATE NULL, scope TEXT NULL, terms TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_mb_proposals_project (project_id), INDEX idx_mb_proposals_estimate (estimate_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_plan_files (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NULL, title VARCHAR(180) NOT NULL, plan_type VARCHAR(80) NOT NULL DEFAULT 'floor_plan', revision VARCHAR(40) NULL, status VARCHAR(32) NOT NULL DEFAULT 'draft', file_path VARCHAR(255) NULL, notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_mb_plan_files_project (project_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_expenses (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NULL, expense_date DATE NOT NULL, category VARCHAR(120) NULL, vendor VARCHAR(180) NULL, description TEXT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0, tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0, reference_no VARCHAR(120) NULL, status VARCHAR(32) NOT NULL DEFAULT 'recorded', created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_mb_expenses_project (project_id), INDEX idx_mb_expenses_date (expense_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_invoices (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NULL, invoice_no VARCHAR(80) NULL UNIQUE, client_name VARCHAR(180) NULL, issue_date DATE NOT NULL, due_date DATE NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0, paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(32) NOT NULL DEFAULT 'unpaid', notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_employees (id INT AUTO_INCREMENT PRIMARY KEY, employee_code VARCHAR(64) NULL UNIQUE, full_name VARCHAR(180) NOT NULL, employee_type VARCHAR(80) NULL, job_title VARCHAR(120) NULL, department VARCHAR(120) NULL, phone VARCHAR(64) NULL, email VARCHAR(180) NULL, daily_rate DECIMAL(12,2) NOT NULL DEFAULT 0, status VARCHAR(32) NOT NULL DEFAULT 'active', notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_attendance (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, project_id INT NULL, attendance_date DATE NOT NULL, time_in TIME NULL, time_out TIME NULL, status VARCHAR(32) NOT NULL DEFAULT 'present', notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_mb_attendance_day (employee_id, attendance_date), FOREIGN KEY (employee_id) REFERENCES mb_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_inventory_items (id INT AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(80) NULL UNIQUE, item_name VARCHAR(180) NOT NULL, category VARCHAR(120) NULL, unit VARCHAR(40) NULL, quantity DECIMAL(14,2) NOT NULL DEFAULT 0, min_quantity DECIMAL(14,2) NOT NULL DEFAULT 0, unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0, location VARCHAR(180) NULL, status VARCHAR(32) NOT NULL DEFAULT 'active', notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_documents (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NULL, related_type VARCHAR(80) NULL, related_id INT NULL, title VARCHAR(180) NOT NULL, category VARCHAR(120) NULL, status VARCHAR(32) NOT NULL DEFAULT 'active', file_path VARCHAR(255) NULL, expiry_date DATE NULL, notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_mb_documents_project (project_id), INDEX idx_mb_documents_category (category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

-- Professional Estimate Builder expansion. Safe for existing mb_estimates installations.
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS project_type VARCHAR(80) NULL;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS floor_area DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS floors INT NOT NULL DEFAULT 1;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS duration_days INT NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS target_start_date DATE NULL;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS target_end_date DATE NULL;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS professional_fee DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS permit_fee DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS mobilization_fee DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS supervision_fee DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS contingency_percent DECIMAL(8,2) NOT NULL DEFAULT 10;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS contingency_amount DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS target_margin_percent DECIMAL(8,2) NOT NULL DEFAULT 15;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS profit_amount DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS profit_margin_percent DECIMAL(8,2) NOT NULL DEFAULT 0;
ALTER TABLE mb_estimates ADD COLUMN IF NOT EXISTS risk_level VARCHAR(32) NOT NULL DEFAULT 'review';

CREATE TABLE IF NOT EXISTS mb_estimate_materials (
 id INT AUTO_INCREMENT PRIMARY KEY, estimate_id INT NOT NULL, material_name VARCHAR(180) NOT NULL, unit VARCHAR(40) NULL, quantity DECIMAL(14,3) NOT NULL DEFAULT 0, unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0, waste_percent DECIMAL(8,2) NOT NULL DEFAULT 0, supplier VARCHAR(180) NULL, line_total DECIMAL(14,2) NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_mb_estimate_materials_estimate (estimate_id), FOREIGN KEY (estimate_id) REFERENCES mb_estimates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_estimate_labor (
 id INT AUTO_INCREMENT PRIMARY KEY, estimate_id INT NOT NULL, role_name VARCHAR(180) NOT NULL, worker_count DECIMAL(10,2) NOT NULL DEFAULT 0, daily_rate DECIMAL(14,2) NOT NULL DEFAULT 0, days_count DECIMAL(10,2) NOT NULL DEFAULT 0, line_total DECIMAL(14,2) NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_mb_estimate_labor_estimate (estimate_id), FOREIGN KEY (estimate_id) REFERENCES mb_estimates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mb_estimate_equipment (
 id INT AUTO_INCREMENT PRIMARY KEY, estimate_id INT NOT NULL, equipment_name VARCHAR(180) NOT NULL, rate_type VARCHAR(40) NOT NULL DEFAULT 'daily', rate DECIMAL(14,2) NOT NULL DEFAULT 0, duration DECIMAL(10,2) NOT NULL DEFAULT 0, line_total DECIMAL(14,2) NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_mb_estimate_equipment_estimate (estimate_id), FOREIGN KEY (estimate_id) REFERENCES mb_estimates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Workspace SPA professional estimate/proposal/project enhancement
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS project_type VARCHAR(80) NULL;
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS payment_terms TEXT NULL;
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS exclusions TEXT NULL;
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS timeline_days INT NOT NULL DEFAULT 0;
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL;
ALTER TABLE mb_proposals ADD COLUMN IF NOT EXISTS approved_by INT NULL;
ALTER TABLE mb_projects ADD COLUMN IF NOT EXISTS contract_start_date DATE NULL;
ALTER TABLE mb_projects ADD COLUMN IF NOT EXISTS site_contact VARCHAR(180) NULL;
ALTER TABLE mb_projects ADD COLUMN IF NOT EXISTS priority VARCHAR(32) NOT NULL DEFAULT 'normal';
CREATE TABLE IF NOT EXISTS mb_project_milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  target_date DATE NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_project_milestones_project(project_id),
  CONSTRAINT fk_mb_project_milestones_project FOREIGN KEY(project_id) REFERENCES mb_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
