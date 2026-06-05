<?php
// helpers.php

// Start session if not started yet (safe across includes)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('calc_purchase')) {
    function calc_purchase($vatable, $non_vat, $net, $vat_nvat = 'VAT') {
        // Normalize numeric inputs
        $vatable = floatval($vatable);
        $non_vat = floatval($non_vat);
        $net     = floatval($net);

        $mode     = strtoupper((string)$vat_nvat);
        $isNonVAT = ($mode === 'NONVAT' || $mode === 'NON-VAT');

        if ($isNonVAT) {
            // NON-VAT MODE:
            // If Net is entered, NonVAT = Net, other computed fields = 0
            if ($net > 0) {
                $non_vat = round($net, 2);
            } else {
                // If no net provided, keep provided non_vat as-is (rounded)
                $non_vat = round($non_vat, 2);
            }

            $vatable   = 0.00;
            $input_vat = 0.00;
            $total     = round($non_vat, 2);
            $cash      = $total; // no VAT in NON-VAT mode
        } else {
            // VAT MODE:
            // Enforce business rule: NON-VAT must be ignored/cleared
            $non_vat = 0.00;

            // If Net provided, derive vatable from net; else use given vatable
            if ($net > 0) {
                $vatable = round($net / 1.12, 2);
            } else {
                $vatable = round($vatable, 2);
            }

            $input_vat = round($vatable * 0.12, 2);
            $total     = round($vatable + $non_vat, 2); // effectively just vatable
            $cash      = round($input_vat + $total, 2); // input_vat + vatable
        }

        return [
            "vatable"   => $vatable,
            "non_vat"   => $non_vat,
            "input_vat" => $input_vat,
            "total"     => $total,
            "cash"      => $cash
        ];
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('redirect_if_not_logged_in')) {
    function redirect_if_not_logged_in() {
        if (!is_logged_in()) {
            header("Location: login.php");
            exit;
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sent = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $stored = (string)($_SESSION['_csrf_token'] ?? '');
        if ($sent === '' || $stored === '' || !hash_equals($stored, $sent)) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}

/* --------------------------------------------------------------------------
 * Roles (staff/admin/accounting/warehouse/client)
 * -------------------------------------------------------------------------- */
if (!function_exists('ensure_table_user_roles')) {
    function ensure_table_user_roles(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT PRIMARY KEY,
            role VARCHAR(32) NOT NULL DEFAULT 'staff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('is_valid_email_address')) {
    function is_valid_email_address(string $email): bool {
        $email = trim($email);
        if ($email === '' || str_contains($email, ' ') || !str_contains($email, '@')) {
            return false;
        }
        [$localPart, $domainPart] = array_pad(explode('@', $email, 2), 2, '');
        if ($localPart === '' || $domainPart === '') {
            return false;
        }
        if (function_exists('filter_var') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return (bool)preg_match('/^[^@\s]+@[^@\s]+$/', $email);
    }
}

if (!function_exists('permission_catalog')) {
    function permission_catalog(): array {
        return [
            'access_admin_panel' => 'Access Admin Panel',
            'manage_roles' => 'Manage Roles and Permissions',
            'manage_staff' => 'Manage Staff Accounts',
            'view_account_dashboard' => 'View Account Dashboard',
            'view_journal' => 'View Journal',
            'create_journal' => 'Create Journal Entries',
            'edit_journal' => 'Edit Journal Entries',
            'delete_journal' => 'Delete Journal Entries',
            'export_journal' => 'Export Journal',
            'import_journal' => 'Import Journal',
            'view_inquiries' => 'View Inquiries',
            'manage_company_content' => 'Manage Company Content',
            'manage_client_portal' => 'Manage Client Portal',
            'run_database_tools' => 'Run Database Tools',
            'view_projects' => 'View Projects',
            'manage_projects' => 'Manage Projects',
            'view_estimates' => 'View Estimates',
            'manage_estimates' => 'Manage Estimates',
            'view_proposals' => 'View Proposals',
            'manage_proposals' => 'Manage Proposals',
            'view_plans' => 'View Floor and Architectural Plans',
            'manage_plans' => 'Manage Floor and Architectural Plans',
            'view_finance' => 'View Finance Workspace',
            'manage_expenses' => 'Manage Expenses',
            'manage_taxes' => 'Manage Taxes',
            'manage_ledgers' => 'Manage Ledgers',
            'manage_bills' => 'Manage Bills',
            'manage_invoices' => 'Manage Invoices',
            'manage_receipts' => 'Manage Receipts',
            'manage_permits' => 'Manage Permits and Fees',
            'view_hr' => 'View HR Workspace',
            'manage_employees' => 'Manage Employees',
            'manage_attendance' => 'Manage Attendance',
            'manage_payroll' => 'Manage Payroll and Salary',
            'manage_insurance' => 'Manage Insurance',
            'view_inventory' => 'View Inventory',
            'manage_inventory' => 'Manage Inventory',
            'view_documents' => 'View Documents',
            'manage_documents' => 'Manage Documents',
            'view_reports' => 'View Reports',
            'manage_tasks' => 'Manage Tasks',
        ];
    }
}

if (!function_exists('default_role_permissions')) {
    function default_role_permissions(): array {
        $all = array_keys(permission_catalog());
        return [
            'admin' => $all,
            'engineer_owner' => [
                'view_account_dashboard','view_projects','manage_projects','view_estimates','manage_estimates',
                'view_proposals','manage_proposals','view_plans','manage_plans','view_finance','view_inventory',
                'view_documents','manage_documents','view_reports','view_inquiries','manage_client_portal','manage_tasks'
            ],
            'assistant' => [
                'view_account_dashboard','view_journal','create_journal','edit_journal','export_journal','import_journal',
                'view_projects','manage_projects','view_estimates','manage_estimates','view_proposals','manage_proposals',
                'view_finance','manage_expenses','manage_taxes','manage_ledgers','manage_bills','manage_invoices','manage_receipts','manage_permits',
                'view_hr','manage_employees','manage_attendance','manage_payroll','manage_insurance',
                'view_documents','manage_documents','view_reports','view_inquiries','manage_client_portal','manage_tasks'
            ],
            'encoder' => [
                'view_account_dashboard','view_journal','create_journal','edit_journal','export_journal','import_journal',
                'view_finance','manage_expenses','manage_ledgers','view_reports'
            ],
            'staff' => [
                'view_account_dashboard','view_projects','view_inventory','manage_inventory','view_documents','manage_documents','view_reports','view_inquiries','manage_tasks'
            ],
            'accounting' => [
                'view_account_dashboard','view_journal','create_journal','edit_journal','delete_journal','export_journal','import_journal','view_finance','manage_expenses','manage_ledgers','view_reports','view_inquiries'
            ],
            'warehouse' => [
                'view_account_dashboard','view_journal','create_journal','view_inventory','manage_inventory','view_projects'
            ],
        ];
    }
}

if (!function_exists('ensure_roles_permissions_tables')) {
    function ensure_roles_permissions_tables(PDO $pdo): void {
        ensure_table_user_roles($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_slug VARCHAR(64) NOT NULL,
            permission_key VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_role_permission (role_slug, permission_key),
            INDEX idx_role_permissions_role (role_slug),
            FOREIGN KEY (role_slug) REFERENCES roles(slug) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $defaults = [
            ['admin', 'Administrator', 1],
            ['engineer_owner', 'Engineer / Owner', 1],
            ['assistant', 'Assistant', 1],
            ['encoder', 'Encoder', 1],
            ['staff', 'Staff', 1],
            ['accounting', 'Accounting (Legacy)', 1],
            ['warehouse', 'Warehouse (Legacy)', 1],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (slug, name, is_system) VALUES (?, ?, ?)");
        foreach ($defaults as [$slug, $name, $isSystem]) {
            $stmt->execute([$slug, $name, $isSystem]);
        }

        $permStmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, permission_key) VALUES (?, ?)");
        foreach (default_role_permissions() as $roleSlug => $permissions) {
            foreach ($permissions as $permission) {
                $permStmt->execute([$roleSlug, $permission]);
            }
        }
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(PDO $pdo): string {
        if (empty($_SESSION['user_id'])) return 'guest';
        ensure_table_user_roles($pdo);
        $st = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ? LIMIT 1");
        $st->execute([(int)$_SESSION['user_id']]);
        $r = $st->fetchColumn();
        return $r ? (string)$r : 'staff';
    }
}

if (!function_exists('current_user_permissions')) {
    function current_user_permissions(PDO $pdo): array {
        if (empty($_SESSION['user_id'])) {
            return [];
        }
        ensure_roles_permissions_tables($pdo);
        $role = current_user_role($pdo);
        if ($role === 'admin') {
            return array_keys(permission_catalog());
        }
        $st = $pdo->prepare("SELECT rp.permission_key
            FROM user_roles ur
            INNER JOIN role_permissions rp ON rp.role_slug = ur.role
            WHERE ur.user_id = ?");
        $st->execute([(int)$_SESSION['user_id']]);
        $perms = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_unique(array_map('strval', $perms)));
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(PDO $pdo, string $permission): bool {
        if (current_user_role($pdo) === 'admin') {
            return true;
        }
        return in_array($permission, current_user_permissions($pdo), true);
    }
}

if (!function_exists('require_role')) {
    function require_role(PDO $pdo, array $roles): void {
        $role = current_user_role($pdo);
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            echo "Forbidden";
            exit;
        }
    }
}

if (!function_exists('current_user_is_admin')) {
    function current_user_is_admin(PDO $pdo): bool {
        return current_user_role($pdo) === 'admin';
    }
}

if (!function_exists('require_admin')) {
    function require_admin(PDO $pdo): void {
        require_role($pdo, ['admin']);
    }
}

if (!function_exists('require_permission')) {
    function require_permission(PDO $pdo, string $permission): void {
        if (!current_user_can($pdo, $permission)) {
            http_response_code(403);
            echo "Forbidden";
            exit;
        }
    }
}

if (!function_exists('ensure_staff_profiles_table')) {
    function ensure_staff_profiles_table(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_profiles (
            user_id INT PRIMARY KEY,
            job_title VARCHAR(120) NULL,
            department VARCHAR(120) NULL,
            phone VARCHAR(64) NULL,
            address VARCHAR(255) NULL,
            bio TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('ensure_staff_activity_log_table')) {
    function ensure_staff_activity_log_table(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_activity_log (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('ensure_content_catalog_tables')) {
    function ensure_content_catalog_tables(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL UNIQUE,
            title VARCHAR(160) NOT NULL,
            location VARCHAR(160) NULL,
            year VARCHAR(16) NULL,
            type VARCHAR(64) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'Ongoing',
            cover VARCHAR(255) NULL,
            before_image VARCHAR(255) NULL,
            after_image VARCHAR(255) NULL,
            summary TEXT NULL,
            materials TEXT NULL,
            gallery TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS website_project_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            media_type VARCHAR(32) NOT NULL DEFAULT 'gallery',
            path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_media_project (project_id, created_at),
            FOREIGN KEY (project_id) REFERENCES website_projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS website_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            desc_text TEXT NOT NULL,
            href VARCHAR(255) NULL,
            range_text VARCHAR(120) NULL,
            timeline_text VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('log_staff_activity')) {
    function log_staff_activity(PDO $pdo, int $userId, string $action, ?string $details = null, ?int $actorId = null): void {
        ensure_staff_activity_log_table($pdo);
        $st = $pdo->prepare("INSERT INTO staff_activity_log (user_id, actor_id, action, details) VALUES (?, ?, ?, ?)");
        $st->execute([$userId, $actorId, $action, $details]);
    }
}



/* --------------------------------------------------------------------------
 * Client Portal helpers
 * -------------------------------------------------------------------------- */
if (!function_exists('ensure_client_portal_tables')) {
    function ensure_client_portal_tables(PDO $pdo): void {
        // Clients
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(64) NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Client access to projects (project_id matches public/data/projects.php ids, e.g. p1)
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_client_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            project_id VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_client_project (client_id, project_id),
            FOREIGN KEY (client_id) REFERENCES website_clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Per-project status info (editable by staff)
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_project_status (
            project_id VARCHAR(64) PRIMARY KEY,
            status_label VARCHAR(64) NOT NULL DEFAULT 'Ongoing',
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            start_date DATE NULL,
            target_end_date DATE NULL,
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            note TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Secure files (stored in /storage/uploads/project_files/, served via /client/download.php)
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_project_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id VARCHAR(64) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            kind VARCHAR(64) NOT NULL DEFAULT 'Document',
            display_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime VARCHAR(120) NULL,
            size_bytes INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Payment schedule
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_project_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id VARCHAR(64) NOT NULL,
            due_date DATE NULL,
            label VARCHAR(160) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
            paid_at DATE NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

/* --------------------------------------------------------------------------
 * Site settings / maintenance mode
 * -------------------------------------------------------------------------- */
if (!function_exists('ensure_site_settings_table')) {
    function ensure_site_settings_table(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value LONGTEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
            ('maintenance_mode', '0'),
            ('maintenance_message', 'We are currently doing maintenance. Please check back later.'),
            ('maintenance_retry_after', '3600')");
    }
}

if (!function_exists('site_setting_get')) {
    function site_setting_get(PDO $pdo, string $key, $default = null) {
        ensure_site_settings_table($pdo);
        $st = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
        $st->execute([$key]);
        $value = $st->fetchColumn();
        return $value === false ? $default : $value;
    }
}

if (!function_exists('site_setting_set')) {
    function site_setting_set(PDO $pdo, string $key, string $value): void {
        ensure_site_settings_table($pdo);
        $st = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $st->execute([$key, $value]);
    }
}

if (!function_exists('maintenance_mode_is_enabled')) {
    function maintenance_mode_is_enabled(PDO $pdo): bool {
        return (string)site_setting_get($pdo, 'maintenance_mode', '0') === '1';
    }
}

if (!function_exists('maintenance_mode_message')) {
    function maintenance_mode_message(PDO $pdo): string {
        return trim((string)site_setting_get($pdo, 'maintenance_message', 'We are currently doing maintenance. Please check back later.')) ?: 'We are currently doing maintenance. Please check back later.';
    }
}

if (!function_exists('is_client_logged_in')) {
    function is_client_logged_in(): bool {
        return !empty($_SESSION['client_id']);
    }
}

if (!function_exists('redirect_client_if_not_logged_in')) {
    function redirect_client_if_not_logged_in(): void {
        if (!is_client_logged_in()) {
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('client_can_access_project')) {
    function client_can_access_project(PDO $pdo, int $client_id, string $project_id): bool {
        ensure_client_portal_tables($pdo);
        $st = $pdo->prepare("SELECT 1 FROM website_client_projects WHERE client_id=? AND project_id=? LIMIT 1");
        $st->execute([$client_id, $project_id]);
        return (bool)$st->fetchColumn();
    }
}

/* --------------------------------------------------------------------------
 * Maorin Builders full workspace upgrade helpers
 * -------------------------------------------------------------------------- */
if (!function_exists('mb_base_url')) {
    function mb_base_url(string $path = ''): string {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('#/(modules|client|public)(/.*)?$#', '', dirname($script));
        if ($base === '/' || $base === '\\' || $base === '.') { $base = ''; }
        $path = ltrim($path, '/');
        return rtrim($base, '/') . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('mb_money')) {
    function mb_money($amount): string { return number_format((float)$amount, 2); }
}

if (!function_exists('mb_require_any_permission')) {
    function mb_require_any_permission(PDO $pdo, array $permissions): void {
        foreach ($permissions as $permission) {
            if (current_user_can($pdo, $permission)) { return; }
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

if (!function_exists('ensure_maorin_workspace_tables')) {
    function ensure_maorin_workspace_tables(PDO $pdo): void {
        ensure_roles_permissions_tables($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_code VARCHAR(64) NULL UNIQUE,
            name VARCHAR(180) NOT NULL,
            client_name VARCHAR(180) NULL,
            client_email VARCHAR(180) NULL,
            client_phone VARCHAR(64) NULL,
            location VARCHAR(255) NULL,
            project_type VARCHAR(80) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'proposed',
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            start_date DATE NULL,
            target_end_date DATE NULL,
            estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            actual_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            contract_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mb_projects_status (status),
            INDEX idx_mb_projects_client (client_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_project_updates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            update_date DATE NOT NULL,
            title VARCHAR(180) NOT NULL,
            progress_percent TINYINT UNSIGNED NULL,
            cost_added DECIMAL(14,2) NOT NULL DEFAULT 0,
            details TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mb_project_updates_project (project_id, update_date),
            FOREIGN KEY (project_id) REFERENCES mb_projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_estimates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NULL,
            estimate_no VARCHAR(64) NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            client_name VARCHAR(180) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            labor_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            material_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            equipment_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            overhead_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            markup_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
            tax_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
            markup_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mb_estimates_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_proposals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NULL,
            estimate_id INT NULL,
            proposal_no VARCHAR(64) NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            client_name VARCHAR(180) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            valid_until DATE NULL,
            scope TEXT NULL,
            terms TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mb_proposals_project (project_id),
            INDEX idx_mb_proposals_estimate (estimate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_plan_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NULL,
            title VARCHAR(180) NOT NULL,
            plan_type VARCHAR(80) NOT NULL DEFAULT 'floor_plan',
            revision VARCHAR(40) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            file_path VARCHAR(255) NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mb_plan_files_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NULL,
            expense_date DATE NOT NULL,
            category VARCHAR(120) NULL,
            vendor VARCHAR(180) NULL,
            description TEXT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            reference_no VARCHAR(120) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'recorded',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mb_expenses_project (project_id),
            INDEX idx_mb_expenses_date (expense_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NULL,
            invoice_no VARCHAR(80) NULL UNIQUE,
            client_name VARCHAR(180) NULL,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'unpaid',
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(64) NULL UNIQUE,
            full_name VARCHAR(180) NOT NULL,
            employee_type VARCHAR(80) NULL,
            job_title VARCHAR(120) NULL,
            department VARCHAR(120) NULL,
            phone VARCHAR(64) NULL,
            email VARCHAR(180) NULL,
            daily_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            project_id INT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME NULL,
            time_out TIME NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'present',
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_mb_attendance_day (employee_id, attendance_date),
            FOREIGN KEY (employee_id) REFERENCES mb_employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_inventory_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(80) NULL UNIQUE,
            item_name VARCHAR(180) NOT NULL,
            category VARCHAR(120) NULL,
            unit VARCHAR(40) NULL,
            quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
            min_quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
            unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
            location VARCHAR(180) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mb_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NULL,
            related_type VARCHAR(80) NULL,
            related_id INT NULL,
            title VARCHAR(180) NOT NULL,
            category VARCHAR(120) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            file_path VARCHAR(255) NULL,
            expiry_date DATE NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mb_documents_project (project_id),
            INDEX idx_mb_documents_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
