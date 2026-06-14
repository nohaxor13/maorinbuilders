CREATE TABLE IF NOT EXISTS estimator_project_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(80) NULL,
    measurement_type VARCHAR(40) NOT NULL DEFAULT 'sqm',
    default_duration_min_days INT NOT NULL DEFAULT 7,
    default_duration_max_days INT NOT NULL DEFAULT 30,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_finish_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type_id INT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    base_rate_per_sqm DECIMAL(15,2) NOT NULL DEFAULT 0,
    multiplier DECIMAL(10,4) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estimator_finish_project (project_type_id),
    CONSTRAINT fk_estimator_finish_project FOREIGN KEY (project_type_id) REFERENCES estimator_project_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_scope_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type_id INT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    calculation_type VARCHAR(40) NOT NULL DEFAULT 'fixed',
    amount_value DECIMAL(15,4) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estimator_scope_project (project_type_id),
    CONSTRAINT fk_estimator_scope_project FOREIGN KEY (project_type_id) REFERENCES estimator_project_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_location_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(180) NOT NULL,
    barangay VARCHAR(180) NULL,
    multiplier DECIMAL(10,4) NOT NULL DEFAULT 1,
    fixed_surcharge DECIMAL(15,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_site_condition_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    calculation_type VARCHAR(40) NOT NULL DEFAULT 'fixed',
    amount_value DECIMAL(15,4) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_timeline_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    multiplier DECIMAL(10,4) NOT NULL DEFAULT 1,
    fixed_surcharge DECIMAL(15,2) NOT NULL DEFAULT 0,
    duration_adjustment_days INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_settings (
    id INT NOT NULL PRIMARY KEY,
    currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
    low_range_percent DECIMAL(10,4) NOT NULL DEFAULT 0.90,
    high_range_percent DECIMAL(10,4) NOT NULL DEFAULT 1.20,
    contingency_percent DECIMAL(10,4) NOT NULL DEFAULT 0.08,
    profit_margin_percent DECIMAL(10,4) NOT NULL DEFAULT 0.12,
    minimum_estimate_amount DECIMAL(15,2) NOT NULL DEFAULT 50000,
    default_unit VARCHAR(40) NOT NULL DEFAULT 'meter',
    allowed_units_json LONGTEXT NULL,
    require_phone TINYINT(1) NOT NULL DEFAULT 1,
    require_email TINYINT(1) NOT NULL DEFAULT 0,
    enable_sketch_tool TINYINT(1) NOT NULL DEFAULT 1,
    enable_file_upload TINYINT(1) NOT NULL DEFAULT 0,
    max_upload_mb INT NOT NULL DEFAULT 5,
    public_disclaimer_text TEXT NULL,
    drawing_disclaimer_text TEXT NULL,
    intro_text TEXT NULL,
    allowed_file_extensions_json LONGTEXT NULL,
    admin_notification_setting VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type_id INT NULL,
    finish_level_id INT NULL,
    result_id INT NULL,
    drawing_id INT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    full_name VARCHAR(180) NOT NULL,
    mobile_number VARCHAR(60) NULL,
    email VARCHAR(180) NULL,
    preferred_contact_method VARCHAR(40) NULL,
    preferred_consultation_date DATE NULL,
    city VARCHAR(180) NULL,
    barangay VARCHAR(180) NULL,
    project_address TEXT NULL,
    notes TEXT NULL,
    project_description TEXT NULL,
    internal_notes TEXT NULL,
    source_page VARCHAR(120) NULL,
    created_client_id INT NULL,
    created_proposal_id INT NULL,
    created_project_id INT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_lead_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    answer_key VARCHAR(120) NOT NULL,
    answer_label VARCHAR(180) NULL,
    answer_type VARCHAR(40) NOT NULL DEFAULT 'text',
    answer_value LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estimator_answer_lead (lead_id),
    CONSTRAINT fk_estimator_answer_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NULL,
    project_type_id INT NULL,
    finish_level_id INT NULL,
    measurement_method VARCHAR(40) NOT NULL DEFAULT 'manual',
    measurement_value DECIMAL(15,4) NOT NULL DEFAULT 0,
    measurement_unit VARCHAR(40) NULL,
    normalized_area_sqm DECIMAL(15,4) NOT NULL DEFAULT 0,
    normalized_linear_m DECIMAL(15,4) NOT NULL DEFAULT 0,
    normalized_perimeter_m DECIMAL(15,4) NOT NULL DEFAULT 0,
    base_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    scope_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    location_adjustment DECIMAL(15,2) NOT NULL DEFAULT 0,
    site_condition_adjustment DECIMAL(15,2) NOT NULL DEFAULT 0,
    timeline_adjustment DECIMAL(15,2) NOT NULL DEFAULT 0,
    contingency_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    profit_margin_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    computed_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    low_estimate DECIMAL(15,2) NOT NULL DEFAULT 0,
    high_estimate DECIMAL(15,2) NOT NULL DEFAULT 0,
    duration_min_days INT NOT NULL DEFAULT 0,
    duration_max_days INT NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
    formula_snapshot_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_drawings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NULL,
    project_type_id INT NULL,
    unit_name VARCHAR(40) NOT NULL DEFAULT 'meter',
    scale_ratio DECIMAL(15,6) NOT NULL DEFAULT 1,
    canvas_json LONGTEXT NULL,
    preview_image LONGTEXT NULL,
    area_value DECIMAL(15,4) NOT NULL DEFAULT 0,
    area_unit VARCHAR(40) NULL,
    perimeter_value DECIMAL(15,4) NOT NULL DEFAULT 0,
    perimeter_unit VARCHAR(40) NULL,
    normalized_area_sqm DECIMAL(15,4) NOT NULL DEFAULT 0,
    normalized_perimeter_m DECIMAL(15,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_lead_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    note_text TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_estimator_lead_note_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estimator_uploaded_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_ext VARCHAR(20) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_estimator_upload_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
