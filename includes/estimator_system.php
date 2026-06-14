<?php
declare(strict_types=1);

if (!function_exists('mb_estimator_h')) {
    function mb_estimator_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mb_estimator_num')) {
    function mb_estimator_num($value): float {
        return is_numeric($value) ? (float)$value : 0.0;
    }
}

if (!function_exists('mb_estimator_json')) {
    function mb_estimator_json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('mb_estimator_money')) {
    function mb_estimator_money(float $value, string $currency = 'PHP'): string {
        $prefix = strtoupper($currency) === 'PHP' ? 'PHP ' : strtoupper($currency) . ' ';
        if (function_exists('mb_money')) {
            return $prefix . mb_money($value);
        }
        return $prefix . number_format($value, 2);
    }
}

if (!function_exists('mb_estimator_table_exists')) {
    function mb_estimator_table_exists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('mb_estimator_column_exists')) {
    function mb_estimator_column_exists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('mb_estimator_add_column')) {
    function mb_estimator_add_column(PDO $pdo, string $table, string $column, string $definition): void {
        if (!mb_estimator_column_exists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}

if (!function_exists('mb_estimator_settings_defaults')) {
    function mb_estimator_settings_defaults(): array {
        return [
            'currency' => 'PHP',
            'low_range_percent' => 0.90,
            'high_range_percent' => 1.20,
            'contingency_percent' => 0.08,
            'profit_margin_percent' => 0.12,
            'minimum_estimate_amount' => 50000,
            'default_unit' => 'meter',
            'allowed_units' => ['meter', 'centimeter', 'millimeter', 'feet', 'inch'],
            'require_phone' => 1,
            'require_email' => 0,
            'enable_sketch_tool' => 1,
            'enable_file_upload' => 0,
            'max_upload_mb' => 5,
            'public_disclaimer_text' => 'This is not a final quotation. Final pricing requires site inspection, design review, material selection, and admin approval.',
            'drawing_disclaimer_text' => 'This sketch is for preliminary estimating only and is not an architectural, structural, or permit-ready drawing.',
            'intro_text' => 'Use this guided estimator to get a preliminary project cost range based on your scope, measurements, location, and finish preferences.',
            'allowed_file_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
        ];
    }
}

if (!function_exists('mb_estimator_seed_defaults')) {
    function mb_estimator_seed_defaults(PDO $pdo): void {
        $projectTypes = [
            ['new-house-construction', 'New house construction', 'Complete residential construction project.', 'bi-house-door', 'sqm', 120, 240, 1, 10],
            ['house-renovation', 'House renovation', 'Interior or whole-house renovation scope.', 'bi-hammer', 'sqm', 30, 120, 1, 20],
            ['kitchen-renovation', 'Kitchen renovation', 'Kitchen upgrade or reconfiguration.', 'bi-grid-3x3-gap', 'sqm', 15, 45, 1, 30],
            ['bathroom-renovation', 'Bathroom renovation', 'Bathroom refresh or complete renovation.', 'bi-droplet-half', 'sqm', 10, 30, 1, 40],
            ['extension-additional-room', 'Extension / additional room', 'Room extension or annex works.', 'bi-plus-square', 'sqm', 30, 90, 1, 50],
            ['roofing', 'Roofing', 'Roof replacement or new roof works.', 'bi-house-up', 'sqm', 10, 30, 1, 60],
            ['fence-gate', 'Fence / gate', 'Fence line and gate works.', 'bi-border-style', 'linear_meter', 7, 30, 1, 70],
            ['commercial-fit-out', 'Commercial fit-out', 'Office, retail, or commercial interior works.', 'bi-shop', 'sqm', 45, 120, 1, 80],
            ['repair-maintenance', 'Repair / maintenance', 'Repair, patching, and maintenance works.', 'bi-wrench-adjustable', 'sqm', 3, 15, 1, 90],
            ['custom-project', 'Custom project', 'Specialized project with custom measurement rules.', 'bi-stars', 'custom_formula', 10, 60, 1, 100],
        ];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM estimator_project_types WHERE slug = ?");
        $insert = $pdo->prepare("INSERT INTO estimator_project_types (slug,name,description,icon,measurement_type,default_duration_min_days,default_duration_max_days,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($projectTypes as $row) {
            $stmt->execute([$row[0]]);
            if ((int)$stmt->fetchColumn() === 0) {
                $insert->execute($row);
            }
        }

        $scopeItems = [
            ['Design', 'Preliminary design and planning support.', 'percentage', 0.04, 1],
            ['Labor', 'General labor works.', 'multiplier', 0.10, 2],
            ['Materials', 'Core material provision allowance.', 'multiplier', 0.15, 3],
            ['Electrical', 'Electrical rough-in and fixtures allowance.', 'per_sqm', 850, 4],
            ['Plumbing', 'Plumbing rough-in and fixtures allowance.', 'per_sqm', 950, 5],
            ['Tile works', 'Tile installation allowance.', 'per_sqm', 1200, 6],
            ['Ceiling', 'Ceiling and framing allowance.', 'per_sqm', 650, 7],
            ['Painting', 'Painting works allowance.', 'per_sqm', 400, 8],
            ['Roofing', 'Roofing installation allowance.', 'per_sqm', 1800, 9],
            ['Cabinetry', 'Cabinets and casework allowance.', 'fixed', 25000, 10],
            ['Doors/windows', 'Doors and window packages allowance.', 'fixed', 35000, 11],
            ['Structural works', 'Structural strengthening allowance.', 'percentage', 0.07, 12],
            ['Permits assistance', 'Permit processing support allowance.', 'fixed', 15000, 13],
            ['Demolition', 'Demolition works allowance.', 'fixed', 20000, 14],
            ['Clearing', 'Site clearing allowance.', 'fixed', 12000, 15],
            ['Hauling', 'Debris hauling allowance.', 'fixed', 15000, 16],
        ];
        $scopeCheck = $pdo->prepare("SELECT COUNT(*) FROM estimator_scope_items WHERE project_type_id IS NULL AND name = ?");
        $scopeInsert = $pdo->prepare("INSERT INTO estimator_scope_items (project_type_id,name,description,calculation_type,amount_value,is_active,sort_order) VALUES (NULL,?,?,?,?,?,?)");
        foreach ($scopeItems as $row) {
            $scopeCheck->execute([$row[0]]);
            if ((int)$scopeCheck->fetchColumn() === 0) {
                $scopeInsert->execute([$row[0], $row[1], $row[2], $row[3], 1, $row[4]]);
            }
        }

        $timelineRules = [
            ['Flexible', 0.98, 0, 10, 1],
            ['Standard', 1.00, 0, 0, 2],
            ['Rush', 1.12, 15000, -10, 3],
        ];
        $timelineCheck = $pdo->prepare("SELECT COUNT(*) FROM estimator_timeline_rules WHERE name = ?");
        $timelineInsert = $pdo->prepare("INSERT INTO estimator_timeline_rules (name,multiplier,fixed_surcharge,duration_adjustment_days,is_active,sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($timelineRules as $row) {
            $timelineCheck->execute([$row[0]]);
            if ((int)$timelineCheck->fetchColumn() === 0) {
                $timelineInsert->execute([$row[0], $row[1], $row[2], $row[3], 1, $row[4]]);
            }
        }

        $siteRules = [
            ['Site access difficulty', 'Difficult or tight access for manpower and materials.', 'percentage', 0.05, 1],
            ['Existing structure', 'Working around existing structures and occupants.', 'percentage', 0.04, 2],
            ['Needs demolition', 'Demolition preparation and disposal works.', 'fixed', 18000, 3],
            ['Needs clearing', 'Clearing and preparation works.', 'fixed', 12000, 4],
            ['Road access condition', 'Poor road access may add logistics cost.', 'percentage', 0.03, 5],
        ];
        $siteCheck = $pdo->prepare("SELECT COUNT(*) FROM estimator_site_condition_rules WHERE name = ?");
        $siteInsert = $pdo->prepare("INSERT INTO estimator_site_condition_rules (name,description,calculation_type,amount_value,is_active,sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($siteRules as $row) {
            $siteCheck->execute([$row[0]]);
            if ((int)$siteCheck->fetchColumn() === 0) {
                $siteInsert->execute([$row[0], $row[1], $row[2], $row[3], 1, $row[4]]);
            }
        }

        $typeRows = $pdo->query("SELECT id, slug FROM estimator_project_types")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $finishConfig = [
            'budget' => ['Budget', 'Practical and economical finish package.', 18000, 0.92, 10],
            'standard' => ['Standard', 'Balanced finish package for most residential works.', 24000, 1.00, 20],
            'semi-premium' => ['Semi-premium', 'Improved materials and detailing.', 30000, 1.12, 30],
            'premium' => ['Premium', 'Higher-end finish selections and detailing.', 38000, 1.28, 40],
            'custom' => ['Custom', 'Custom finish level subject to review.', 0, 1.00, 50],
        ];
        $finishCheck = $pdo->prepare("SELECT COUNT(*) FROM estimator_finish_levels WHERE project_type_id = ? AND name = ?");
        $finishInsert = $pdo->prepare("INSERT INTO estimator_finish_levels (project_type_id,name,description,base_rate_per_sqm,multiplier,is_active,sort_order) VALUES (?,?,?,?,?,?,?)");
        foreach ($typeRows as $type) {
            $slug = (string)$type['slug'];
            foreach ($finishConfig as $key => $finish) {
                $baseRate = $finish[2];
                if ($slug === 'roofing') {
                    $baseRate = match ($key) {
                        'budget' => 2200,
                        'standard' => 3200,
                        'semi-premium' => 4300,
                        'premium' => 5600,
                        default => 0,
                    };
                } elseif ($slug === 'fence-gate') {
                    $baseRate = match ($key) {
                        'budget' => 3500,
                        'standard' => 5000,
                        'semi-premium' => 6500,
                        'premium' => 8200,
                        default => 0,
                    };
                } elseif ($slug === 'repair-maintenance') {
                    $baseRate = match ($key) {
                        'budget' => 4500,
                        'standard' => 6500,
                        'semi-premium' => 8500,
                        'premium' => 11000,
                        default => 0,
                    };
                }
                $finishCheck->execute([(int)$type['id'], $finish[0]]);
                if ((int)$finishCheck->fetchColumn() === 0) {
                    $finishInsert->execute([(int)$type['id'], $finish[0], $finish[1], $baseRate, $finish[3], 1, $finish[4]]);
                }
            }
        }
    }
}

if (!function_exists('mb_estimator_bootstrap')) {
    function mb_estimator_bootstrap(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_project_types (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_finish_levels (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_scope_items (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_location_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            city VARCHAR(180) NOT NULL,
            barangay VARCHAR(180) NULL,
            multiplier DECIMAL(10,4) NOT NULL DEFAULT 1,
            fixed_surcharge DECIMAL(15,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_estimator_location_city (city),
            INDEX idx_estimator_location_barangay (barangay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_site_condition_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(180) NOT NULL UNIQUE,
            description TEXT NULL,
            calculation_type VARCHAR(40) NOT NULL DEFAULT 'fixed',
            amount_value DECIMAL(15,4) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_timeline_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            multiplier DECIMAL(10,4) NOT NULL DEFAULT 1,
            fixed_surcharge DECIMAL(15,2) NOT NULL DEFAULT 0,
            duration_adjustment_days INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_settings (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_leads (
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_estimator_leads_status (status),
            INDEX idx_estimator_leads_project (project_type_id),
            INDEX idx_estimator_leads_created (created_at),
            CONSTRAINT fk_estimator_lead_project FOREIGN KEY (project_type_id) REFERENCES estimator_project_types(id) ON DELETE SET NULL,
            CONSTRAINT fk_estimator_lead_finish FOREIGN KEY (finish_level_id) REFERENCES estimator_finish_levels(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_lead_answers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            answer_key VARCHAR(120) NOT NULL,
            answer_label VARCHAR(180) NULL,
            answer_type VARCHAR(40) NOT NULL DEFAULT 'text',
            answer_value LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estimator_answer_lead (lead_id),
            INDEX idx_estimator_answer_key (answer_key),
            CONSTRAINT fk_estimator_answer_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_results (
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estimator_result_lead (lead_id),
            CONSTRAINT fk_estimator_result_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE SET NULL,
            CONSTRAINT fk_estimator_result_project FOREIGN KEY (project_type_id) REFERENCES estimator_project_types(id) ON DELETE SET NULL,
            CONSTRAINT fk_estimator_result_finish FOREIGN KEY (finish_level_id) REFERENCES estimator_finish_levels(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_drawings (
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estimator_drawing_lead (lead_id),
            CONSTRAINT fk_estimator_drawing_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE SET NULL,
            CONSTRAINT fk_estimator_drawing_project FOREIGN KEY (project_type_id) REFERENCES estimator_project_types(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_lead_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            note_text TEXT NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estimator_lead_note_lead (lead_id),
            CONSTRAINT fk_estimator_lead_note_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS estimator_uploaded_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_ext VARCHAR(20) NOT NULL,
            file_size INT NOT NULL DEFAULT 0,
            mime_type VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estimator_upload_lead (lead_id),
            CONSTRAINT fk_estimator_upload_lead FOREIGN KEY (lead_id) REFERENCES estimator_leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $defaults = mb_estimator_settings_defaults();
        $settingsStmt = $pdo->prepare("SELECT COUNT(*) FROM estimator_settings WHERE id = 1");
        $settingsStmt->execute();
        if ((int)$settingsStmt->fetchColumn() === 0) {
            $insert = $pdo->prepare("INSERT INTO estimator_settings (
                id,currency,low_range_percent,high_range_percent,contingency_percent,profit_margin_percent,minimum_estimate_amount,default_unit,allowed_units_json,require_phone,require_email,enable_sketch_tool,enable_file_upload,max_upload_mb,public_disclaimer_text,drawing_disclaimer_text,intro_text,allowed_file_extensions_json
            ) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $insert->execute([
                $defaults['currency'],
                $defaults['low_range_percent'],
                $defaults['high_range_percent'],
                $defaults['contingency_percent'],
                $defaults['profit_margin_percent'],
                $defaults['minimum_estimate_amount'],
                $defaults['default_unit'],
                json_encode($defaults['allowed_units'], JSON_UNESCAPED_SLASHES),
                $defaults['require_phone'],
                $defaults['require_email'],
                $defaults['enable_sketch_tool'],
                $defaults['enable_file_upload'],
                $defaults['max_upload_mb'],
                $defaults['public_disclaimer_text'],
                $defaults['drawing_disclaimer_text'],
                $defaults['intro_text'],
                json_encode($defaults['allowed_file_extensions'], JSON_UNESCAPED_SLASHES),
            ]);
        }

        mb_estimator_seed_defaults($pdo);
    }
}

if (!function_exists('mb_estimator_settings')) {
    function mb_estimator_settings(PDO $pdo): array {
        mb_estimator_bootstrap($pdo);
        $stmt = $pdo->query("SELECT * FROM estimator_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $defaults = mb_estimator_settings_defaults();
        $allowedUnits = json_decode((string)($row['allowed_units_json'] ?? ''), true);
        $allowedExt = json_decode((string)($row['allowed_file_extensions_json'] ?? ''), true);
        return [
            'currency' => (string)($row['currency'] ?? $defaults['currency']),
            'low_range_percent' => (float)($row['low_range_percent'] ?? $defaults['low_range_percent']),
            'high_range_percent' => (float)($row['high_range_percent'] ?? $defaults['high_range_percent']),
            'contingency_percent' => (float)($row['contingency_percent'] ?? $defaults['contingency_percent']),
            'profit_margin_percent' => (float)($row['profit_margin_percent'] ?? $defaults['profit_margin_percent']),
            'minimum_estimate_amount' => (float)($row['minimum_estimate_amount'] ?? $defaults['minimum_estimate_amount']),
            'default_unit' => (string)($row['default_unit'] ?? $defaults['default_unit']),
            'allowed_units' => is_array($allowedUnits) && $allowedUnits ? array_values($allowedUnits) : $defaults['allowed_units'],
            'require_phone' => (int)($row['require_phone'] ?? $defaults['require_phone']),
            'require_email' => (int)($row['require_email'] ?? $defaults['require_email']),
            'enable_sketch_tool' => (int)($row['enable_sketch_tool'] ?? $defaults['enable_sketch_tool']),
            'enable_file_upload' => (int)($row['enable_file_upload'] ?? $defaults['enable_file_upload']),
            'max_upload_mb' => (int)($row['max_upload_mb'] ?? $defaults['max_upload_mb']),
            'public_disclaimer_text' => trim((string)($row['public_disclaimer_text'] ?? $defaults['public_disclaimer_text'])),
            'drawing_disclaimer_text' => trim((string)($row['drawing_disclaimer_text'] ?? $defaults['drawing_disclaimer_text'])),
            'intro_text' => trim((string)($row['intro_text'] ?? $defaults['intro_text'])),
            'allowed_file_extensions' => is_array($allowedExt) && $allowedExt ? array_values($allowedExt) : $defaults['allowed_file_extensions'],
        ];
    }
}

if (!function_exists('mb_estimator_get_public_config')) {
    function mb_estimator_get_public_config(PDO $pdo): array {
        $settings = mb_estimator_settings($pdo);
        $projectTypes = $pdo->query("SELECT * FROM estimator_project_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $finishLevels = $pdo->query("SELECT * FROM estimator_finish_levels WHERE is_active = 1 ORDER BY project_type_id IS NULL DESC, project_type_id ASC, sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $scopeItems = $pdo->query("SELECT * FROM estimator_scope_items WHERE is_active = 1 ORDER BY project_type_id IS NULL DESC, project_type_id ASC, sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $timelineRules = $pdo->query("SELECT * FROM estimator_timeline_rules WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $siteRules = $pdo->query("SELECT * FROM estimator_site_condition_rules WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $locationRules = $pdo->query("SELECT * FROM estimator_location_rules WHERE is_active = 1 ORDER BY city ASC, barangay ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return compact('settings', 'projectTypes', 'finishLevels', 'scopeItems', 'timelineRules', 'siteRules', 'locationRules');
    }
}

if (!function_exists('mb_estimator_unit_factor')) {
    function mb_estimator_unit_factor(string $unit): float {
        return match (strtolower(trim($unit))) {
            'meter', 'meters', 'm' => 1.0,
            'centimeter', 'centimeters', 'cm' => 0.01,
            'millimeter', 'millimeters', 'mm' => 0.001,
            'feet', 'foot', 'ft' => 0.3048,
            'inch', 'inches', 'in' => 0.0254,
            default => 1.0,
        };
    }
}

if (!function_exists('mb_estimator_normalize_measurements')) {
    function mb_estimator_normalize_measurements(array $payload, array $projectType): array {
        $method = strtolower(trim((string)($payload['measurement_method'] ?? 'manual')));
        $measurementType = strtolower((string)($projectType['measurement_type'] ?? 'sqm'));
        $unit = strtolower(trim((string)($payload['selected_unit'] ?? $payload['measurement_unit'] ?? 'meter')));
        $factor = mb_estimator_unit_factor($unit);

        $manualArea = mb_estimator_num($payload['manual']['floor_area_sqm'] ?? $payload['floor_area_sqm'] ?? 0);
        $manualLinear = mb_estimator_num($payload['manual']['length_m'] ?? $payload['length_m'] ?? 0);
        $manualRoofArea = mb_estimator_num($payload['manual']['roof_area_sqm'] ?? $payload['roof_area_sqm'] ?? 0);
        $manualAffectedArea = mb_estimator_num($payload['manual']['affected_area_sqm'] ?? $payload['affected_area_sqm'] ?? 0);
        $drawingArea = mb_estimator_num($payload['drawing']['normalized_area_sqm'] ?? 0);
        $drawingPerimeter = mb_estimator_num($payload['drawing']['normalized_perimeter_m'] ?? 0);

        $normalizedArea = 0.0;
        $normalizedLinear = 0.0;
        $normalizedPerimeter = 0.0;

        if ($method === 'sketch') {
            $normalizedArea = max(0, $drawingArea);
            $normalizedPerimeter = max(0, $drawingPerimeter);
            $normalizedLinear = $measurementType === 'linear_meter' ? max(0, $drawingPerimeter) : 0.0;
        } else {
            $normalizedArea = max(0, $manualArea);
            if ($measurementType === 'sqm' && $manualRoofArea > 0) {
                $normalizedArea = $manualRoofArea;
            }
            if (in_array((string)($projectType['slug'] ?? ''), ['house-renovation', 'kitchen-renovation', 'bathroom-renovation', 'repair-maintenance'], true) && $manualAffectedArea > 0) {
                $normalizedArea = $manualAffectedArea;
            }
            $normalizedLinear = max(0, $manualLinear);
            $height = mb_estimator_num($payload['manual']['height_m'] ?? $payload['height_m'] ?? 0);
            if ($measurementType === 'linear_meter' && $normalizedLinear <= 0 && $manualArea > 0) {
                $normalizedLinear = $manualArea;
            }
            if ($normalizedLinear > 0 && $height > 0 && $normalizedArea <= 0) {
                $normalizedArea = $normalizedLinear * $height;
            }
        }

        $measurementValue = $measurementType === 'linear_meter'
            ? ($normalizedLinear > 0 ? $normalizedLinear : $normalizedPerimeter)
            : $normalizedArea;
        if ($measurementType === 'fixed') {
            $measurementValue = 1;
        }
        if ($measurementType === 'custom_formula' && $measurementValue <= 0) {
            $measurementValue = max($normalizedArea, $normalizedLinear, 1);
        }

        return [
            'measurement_method' => $method === 'sketch' ? 'sketch' : 'manual',
            'measurement_type' => $measurementType,
            'measurement_unit' => $measurementType === 'linear_meter' ? 'meter' : 'sqm',
            'selected_unit' => $unit,
            'scale_ratio' => $factor,
            'measurement_value' => $measurementValue,
            'normalized_area_sqm' => $normalizedArea,
            'normalized_linear_m' => $normalizedLinear,
            'normalized_perimeter_m' => $normalizedPerimeter,
        ];
    }
}

if (!function_exists('mb_estimator_location_rule')) {
    function mb_estimator_location_rule(PDO $pdo, string $city, string $barangay = ''): ?array {
        $city = trim($city);
        $barangay = trim($barangay);
        if ($city === '') {
            return null;
        }
        $sql = "SELECT * FROM estimator_location_rules WHERE is_active = 1 AND LOWER(city) = LOWER(?) AND (barangay IS NULL OR barangay = '' OR LOWER(barangay) = LOWER(?)) ORDER BY CASE WHEN barangay IS NULL OR barangay = '' THEN 1 ELSE 0 END ASC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$city, $barangay]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mb_estimator_find_by_id')) {
    function mb_estimator_find_by_id(array $rows, int $id): ?array {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('mb_estimator_calculate')) {
    function mb_estimator_calculate(PDO $pdo, array $payload): array {
        $config = mb_estimator_get_public_config($pdo);
        $settings = $config['settings'];
        $projectTypeId = (int)($payload['project_type_id'] ?? 0);
        $finishLevelId = (int)($payload['finish_level_id'] ?? 0);
        $timelineRuleId = (int)($payload['timeline_rule_id'] ?? 0);
        $scopeItemIds = array_values(array_unique(array_map('intval', (array)($payload['scope_item_ids'] ?? []))));
        $siteRuleIds = array_values(array_unique(array_map('intval', (array)($payload['site_condition_ids'] ?? []))));

        $projectType = mb_estimator_find_by_id($config['projectTypes'], $projectTypeId);
        if (!$projectType) {
            throw new RuntimeException('Please select a valid project type.');
        }
        $finishLevel = mb_estimator_find_by_id($config['finishLevels'], $finishLevelId);
        if (!$finishLevel || (!empty($finishLevel['project_type_id']) && (int)$finishLevel['project_type_id'] !== $projectTypeId)) {
            throw new RuntimeException('Please select a valid finish level.');
        }
        $timelineRule = mb_estimator_find_by_id($config['timelineRules'], $timelineRuleId) ?: [
            'id' => 0,
            'name' => 'Standard',
            'multiplier' => 1,
            'fixed_surcharge' => 0,
            'duration_adjustment_days' => 0,
        ];

        $measurement = mb_estimator_normalize_measurements($payload, $projectType);
        if ($measurement['measurement_type'] !== 'fixed' && $measurement['measurement_value'] <= 0) {
            throw new RuntimeException('Please enter a valid measurement before calculating.');
        }

        $baseRate = max(0, mb_estimator_num($finishLevel['base_rate_per_sqm'] ?? 0));
        $measurementValue = max(0, $measurement['measurement_value']);
        $baseAmount = $measurement['measurement_type'] === 'fixed'
            ? max($baseRate, 1)
            : ($measurementValue * ($baseRate > 0 ? $baseRate : 1));
        $baseAmount *= max(0.01, mb_estimator_num($finishLevel['multiplier'] ?? 1));

        $scopeAmount = 0.0;
        $selectedScopes = [];
        foreach ($config['scopeItems'] as $scope) {
            $scopeId = (int)($scope['id'] ?? 0);
            if (!in_array($scopeId, $scopeItemIds, true)) {
                continue;
            }
            if (!empty($scope['project_type_id']) && (int)$scope['project_type_id'] !== $projectTypeId) {
                continue;
            }
            $calcType = strtolower((string)($scope['calculation_type'] ?? 'fixed'));
            $value = mb_estimator_num($scope['amount_value'] ?? 0);
            $addition = match ($calcType) {
                'per_sqm' => $measurement['normalized_area_sqm'] * $value,
                'per_linear_meter' => max($measurement['normalized_linear_m'], $measurement['normalized_perimeter_m']) * $value,
                'percentage' => $baseAmount * $value,
                'multiplier' => $baseAmount * $value,
                default => $value,
            };
            $scopeAmount += $addition;
            $selectedScopes[] = [
                'id' => $scopeId,
                'name' => (string)$scope['name'],
                'calculation_type' => $calcType,
                'amount_value' => $value,
                'computed_amount' => round($addition, 2),
            ];
        }

        $subtotal = $baseAmount + $scopeAmount;
        $locationRule = mb_estimator_location_rule($pdo, (string)($payload['city'] ?? ''), (string)($payload['barangay'] ?? ''));
        $locationAdjustment = 0.0;
        if ($locationRule) {
            $locationAdjustment = ($subtotal * max(0, mb_estimator_num($locationRule['multiplier'] ?? 1) - 1)) + max(0, mb_estimator_num($locationRule['fixed_surcharge'] ?? 0));
        }

        $siteConditionAdjustment = 0.0;
        $selectedSiteRules = [];
        foreach ($config['siteRules'] as $rule) {
            $ruleId = (int)($rule['id'] ?? 0);
            if (!in_array($ruleId, $siteRuleIds, true)) {
                continue;
            }
            $calcType = strtolower((string)($rule['calculation_type'] ?? 'fixed'));
            $value = mb_estimator_num($rule['amount_value'] ?? 0);
            $addition = match ($calcType) {
                'per_sqm' => $measurement['normalized_area_sqm'] * $value,
                'per_linear_meter' => max($measurement['normalized_linear_m'], $measurement['normalized_perimeter_m']) * $value,
                'percentage' => $subtotal * $value,
                'multiplier' => $subtotal * $value,
                default => $value,
            };
            $siteConditionAdjustment += $addition;
            $selectedSiteRules[] = [
                'id' => $ruleId,
                'name' => (string)$rule['name'],
                'computed_amount' => round($addition, 2),
            ];
        }

        $preTimeline = $subtotal + $locationAdjustment + $siteConditionAdjustment;
        $timelineAdjustment = ($preTimeline * max(0, mb_estimator_num($timelineRule['multiplier'] ?? 1) - 1)) + max(0, mb_estimator_num($timelineRule['fixed_surcharge'] ?? 0));
        $preMargin = $preTimeline + $timelineAdjustment;
        $contingencyAmount = $preMargin * max(0, mb_estimator_num($settings['contingency_percent'] ?? 0));
        $profitMarginAmount = ($preMargin + $contingencyAmount) * max(0, mb_estimator_num($settings['profit_margin_percent'] ?? 0));
        $computedTotal = max((float)$settings['minimum_estimate_amount'], $preMargin + $contingencyAmount + $profitMarginAmount);
        $lowEstimate = max((float)$settings['minimum_estimate_amount'], $computedTotal * max(0.01, (float)$settings['low_range_percent']));
        $highEstimate = max($lowEstimate, $computedTotal * max((float)$settings['high_range_percent'], (float)$settings['low_range_percent']));

        $durationMin = max(1, (int)($projectType['default_duration_min_days'] ?? 0) + (int)($timelineRule['duration_adjustment_days'] ?? 0));
        $durationMax = max($durationMin, (int)($projectType['default_duration_max_days'] ?? 0) + (int)($timelineRule['duration_adjustment_days'] ?? 0));

        $measurementSummary = [
            'method' => $measurement['measurement_method'],
            'type' => $measurement['measurement_type'],
            'measurement_value' => round($measurement['measurement_value'], 2),
            'measurement_unit' => $measurement['measurement_unit'],
            'normalized_area_sqm' => round($measurement['normalized_area_sqm'], 2),
            'normalized_linear_m' => round($measurement['normalized_linear_m'], 2),
            'normalized_perimeter_m' => round($measurement['normalized_perimeter_m'], 2),
            'selected_unit' => $measurement['selected_unit'],
        ];

        $assumptions = [
            'Estimate is based on visitor-provided measurements and scope selections.',
            'Final pricing still depends on site inspection, design details, actual materials, and engineering review.',
            'Admin-configured inclusions, timeline, and location rules were applied to generate this preliminary range.',
        ];
        $exclusions = [
            'Permit fees beyond configured allowances',
            'Owner-supplied specialty materials not yet specified',
            'Structural redesign, soil tests, and hidden site conditions unless later confirmed',
        ];

        $snapshot = [
            'project_type' => $projectType,
            'finish_level' => $finishLevel,
            'timeline_rule' => $timelineRule,
            'measurement' => $measurementSummary,
            'selected_scopes' => $selectedScopes,
            'selected_site_conditions' => $selectedSiteRules,
            'location_rule' => $locationRule,
            'location_input' => [
                'city' => trim((string)($payload['city'] ?? '')),
                'barangay' => trim((string)($payload['barangay'] ?? '')),
                'project_address' => trim((string)($payload['project_address'] ?? '')),
            ],
            'settings' => [
                'currency' => $settings['currency'],
                'low_range_percent' => $settings['low_range_percent'],
                'high_range_percent' => $settings['high_range_percent'],
                'contingency_percent' => $settings['contingency_percent'],
                'profit_margin_percent' => $settings['profit_margin_percent'],
                'minimum_estimate_amount' => $settings['minimum_estimate_amount'],
            ],
        ];

        return [
            'project_type_id' => $projectTypeId,
            'finish_level_id' => $finishLevelId,
            'timeline_rule_id' => (int)($timelineRule['id'] ?? 0),
            'measurement_method' => $measurement['measurement_method'],
            'measurement_value' => round($measurement['measurement_value'], 4),
            'measurement_unit' => $measurement['measurement_unit'],
            'normalized_area_sqm' => round($measurement['normalized_area_sqm'], 4),
            'normalized_linear_m' => round($measurement['normalized_linear_m'], 4),
            'normalized_perimeter_m' => round($measurement['normalized_perimeter_m'], 4),
            'base_amount' => round($baseAmount, 2),
            'scope_amount' => round($scopeAmount, 2),
            'location_adjustment' => round($locationAdjustment, 2),
            'site_condition_adjustment' => round($siteConditionAdjustment, 2),
            'timeline_adjustment' => round($timelineAdjustment, 2),
            'contingency_amount' => round($contingencyAmount, 2),
            'profit_margin_amount' => round($profitMarginAmount, 2),
            'computed_total' => round($computedTotal, 2),
            'low_estimate' => round($lowEstimate, 2),
            'high_estimate' => round($highEstimate, 2),
            'duration_min_days' => $durationMin,
            'duration_max_days' => $durationMax,
            'currency' => $settings['currency'],
            'measurement_summary' => $measurementSummary,
            'selected_scopes' => $selectedScopes,
            'selected_site_conditions' => $selectedSiteRules,
            'timeline_rule' => $timelineRule,
            'project_type' => $projectType,
            'finish_level' => $finishLevel,
            'assumptions' => $assumptions,
            'exclusions' => $exclusions,
            'disclaimer' => $settings['public_disclaimer_text'],
            'range_display' => 'Estimated preliminary range: ' . mb_estimator_money($lowEstimate, $settings['currency']) . ' - ' . mb_estimator_money($highEstimate, $settings['currency']),
            'formula_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES),
        ];
    }
}

if (!function_exists('mb_estimator_validate_drawing')) {
    function mb_estimator_validate_drawing(array $drawing, array $settings): array {
        $unit = strtolower(trim((string)($drawing['unit'] ?? $settings['default_unit'])));
        if (!in_array($unit, array_map('strtolower', $settings['allowed_units']), true)) {
            $unit = strtolower($settings['default_unit']);
        }
        $json = (string)($drawing['canvas_json'] ?? '');
        if (strlen($json) > 750000) {
            throw new RuntimeException('Drawing data is too large.');
        }
        $preview = (string)($drawing['preview_image'] ?? '');
        if (strlen($preview) > 950000) {
            throw new RuntimeException('Drawing preview image is too large.');
        }
        return [
            'unit' => $unit,
            'scale_ratio' => max(0.0001, mb_estimator_num($drawing['scale_ratio'] ?? 1)),
            'canvas_json' => $json,
            'preview_image' => $preview,
            'area_value' => max(0, mb_estimator_num($drawing['area_value'] ?? 0)),
            'area_unit' => trim((string)($drawing['area_unit'] ?? 'sqm')) ?: 'sqm',
            'perimeter_value' => max(0, mb_estimator_num($drawing['perimeter_value'] ?? 0)),
            'perimeter_unit' => trim((string)($drawing['perimeter_unit'] ?? 'm')) ?: 'm',
            'normalized_area_sqm' => max(0, mb_estimator_num($drawing['normalized_area_sqm'] ?? 0)),
            'normalized_perimeter_m' => max(0, mb_estimator_num($drawing['normalized_perimeter_m'] ?? 0)),
        ];
    }
}

if (!function_exists('mb_estimator_save_upload')) {
    function mb_estimator_save_upload(array $file, array $settings): ?array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }
        $maxBytes = max(1, (int)$settings['max_upload_mb']) * 1024 * 1024;
        if ((int)($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Uploaded file is too large.');
        }
        $original = (string)($file['name'] ?? 'attachment');
        $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        $allowed = array_map('strtolower', (array)$settings['allowed_file_extensions']);
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Unsupported upload file type.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = (string)(mime_content_type($tmp) ?: '');
        $mimeAllowed = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'pdf' => ['application/pdf'],
        ];
        if (isset($mimeAllowed[$ext]) && !in_array($mime, $mimeAllowed[$ext], true)) {
            throw new RuntimeException('Upload file content does not match its extension.');
        }
        $dir = dirname(__DIR__) . '/storage/uploads/estimator';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create estimator upload directory.');
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($original)) ?: 'attachment.' . $ext;
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Could not save uploaded file.');
        }
        return [
            'original_name' => $original,
            'file_path' => 'storage/uploads/estimator/' . $name,
            'file_ext' => $ext,
            'file_size' => (int)($file['size'] ?? 0),
            'mime_type' => $mime,
        ];
    }
}

if (!function_exists('mb_estimator_save_submission')) {
    function mb_estimator_save_submission(PDO $pdo, array $payload, array $result, array $drawing = [], ?array $upload = null): int {
        $settings = mb_estimator_settings($pdo);
        $fullName = trim((string)($payload['lead']['full_name'] ?? ''));
        $mobile = trim((string)($payload['lead']['mobile_number'] ?? ''));
        $email = trim((string)($payload['lead']['email'] ?? ''));
        if ($fullName === '') {
            throw new RuntimeException('Full name is required.');
        }
        if (!empty($settings['require_phone']) && $mobile === '') {
            throw new RuntimeException('Mobile number is required.');
        }
        if (!empty($settings['require_email']) && $email === '') {
            throw new RuntimeException('Email is required.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        $pdo->beginTransaction();
        try {
            $leadStmt = $pdo->prepare("INSERT INTO estimator_leads (
                project_type_id,finish_level_id,status,full_name,mobile_number,email,preferred_contact_method,preferred_consultation_date,city,barangay,project_address,notes,project_description,source_page,ip_address,user_agent
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $leadStmt->execute([
                $result['project_type_id'],
                $result['finish_level_id'],
                'new',
                $fullName,
                $mobile ?: null,
                $email ?: null,
                trim((string)($payload['lead']['preferred_contact_method'] ?? '')) ?: null,
                ($payload['lead']['preferred_consultation_date'] ?? null) ?: null,
                trim((string)($payload['city'] ?? '')) ?: null,
                trim((string)($payload['barangay'] ?? '')) ?: null,
                trim((string)($payload['project_address'] ?? '')) ?: null,
                trim((string)($payload['lead']['notes'] ?? '')) ?: null,
                trim((string)($payload['lead']['project_description'] ?? '')) ?: null,
                'public_estimator',
                substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
            $leadId = (int)$pdo->lastInsertId();

            $resultStmt = $pdo->prepare("INSERT INTO estimator_results (
                lead_id,project_type_id,finish_level_id,measurement_method,measurement_value,measurement_unit,normalized_area_sqm,normalized_linear_m,normalized_perimeter_m,base_amount,scope_amount,location_adjustment,site_condition_adjustment,timeline_adjustment,contingency_amount,profit_margin_amount,computed_total,low_estimate,high_estimate,duration_min_days,duration_max_days,currency,formula_snapshot_json
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $resultStmt->execute([
                $leadId,
                $result['project_type_id'],
                $result['finish_level_id'],
                $result['measurement_method'],
                $result['measurement_value'],
                $result['measurement_unit'],
                $result['normalized_area_sqm'],
                $result['normalized_linear_m'],
                $result['normalized_perimeter_m'],
                $result['base_amount'],
                $result['scope_amount'],
                $result['location_adjustment'],
                $result['site_condition_adjustment'],
                $result['timeline_adjustment'],
                $result['contingency_amount'],
                $result['profit_margin_amount'],
                $result['computed_total'],
                $result['low_estimate'],
                $result['high_estimate'],
                $result['duration_min_days'],
                $result['duration_max_days'],
                $result['currency'],
                $result['formula_snapshot_json'],
            ]);
            $resultId = (int)$pdo->lastInsertId();

            $drawingId = null;
            if ($drawing) {
                $drawingStmt = $pdo->prepare("INSERT INTO estimator_drawings (
                    lead_id,project_type_id,unit_name,scale_ratio,canvas_json,preview_image,area_value,area_unit,perimeter_value,perimeter_unit,normalized_area_sqm,normalized_perimeter_m
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $drawingStmt->execute([
                    $leadId,
                    $result['project_type_id'],
                    $drawing['unit'],
                    $drawing['scale_ratio'],
                    $drawing['canvas_json'] ?: null,
                    $drawing['preview_image'] ?: null,
                    $drawing['area_value'],
                    $drawing['area_unit'],
                    $drawing['perimeter_value'],
                    $drawing['perimeter_unit'],
                    $drawing['normalized_area_sqm'],
                    $drawing['normalized_perimeter_m'],
                ]);
                $drawingId = (int)$pdo->lastInsertId();
            }

            if ($upload) {
                $uploadStmt = $pdo->prepare("INSERT INTO estimator_uploaded_files (lead_id,original_name,file_path,file_ext,file_size,mime_type) VALUES (?,?,?,?,?,?)");
                $uploadStmt->execute([
                    $leadId,
                    $upload['original_name'],
                    $upload['file_path'],
                    $upload['file_ext'],
                    $upload['file_size'],
                    $upload['mime_type'],
                ]);
            }

            $answers = [
                ['measurement_method', 'Measurement method', 'text', $payload['measurement_method'] ?? 'manual'],
                ['finish_level_id', 'Finish level', 'number', (string)($payload['finish_level_id'] ?? '')],
                ['timeline_rule_id', 'Timeline preference', 'number', (string)($payload['timeline_rule_id'] ?? '')],
                ['scope_item_ids', 'Scope inclusions', 'json', json_encode(array_values((array)($payload['scope_item_ids'] ?? [])), JSON_UNESCAPED_SLASHES)],
                ['site_condition_ids', 'Site conditions', 'json', json_encode(array_values((array)($payload['site_condition_ids'] ?? [])), JSON_UNESCAPED_SLASHES)],
                ['manual_inputs', 'Manual inputs', 'json', json_encode((array)($payload['manual'] ?? []), JSON_UNESCAPED_SLASHES)],
                ['drawing_inputs', 'Drawing inputs', 'json', json_encode((array)($payload['drawing'] ?? []), JSON_UNESCAPED_SLASHES)],
            ];
            $answerStmt = $pdo->prepare("INSERT INTO estimator_lead_answers (lead_id,answer_key,answer_label,answer_type,answer_value) VALUES (?,?,?,?,?)");
            foreach ($answers as $answer) {
                $answerStmt->execute([$leadId, $answer[0], $answer[1], $answer[2], $answer[3]]);
            }

            $pdo->prepare("UPDATE estimator_leads SET result_id = ?, drawing_id = ? WHERE id = ?")->execute([$resultId, $drawingId, $leadId]);
            $pdo->commit();
            return $leadId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
