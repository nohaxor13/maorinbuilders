<?php
declare(strict_types=1);

if (!function_exists('mb_accounting_journal_types')) {
    function mb_accounting_journal_types(): array {
        return [
            'general' => [
                'label' => 'General Journal',
                'short' => 'General',
                'entry_label' => 'New General Journal Entry',
                'prefix' => 'GJ',
                'columns' => ['entry_date','particulars','ref_page','jv_no','debit','credit'],
            ],
            'sales' => [
                'label' => 'Sales Journal',
                'short' => 'Sales',
                'entry_label' => 'New Sales Journal Entry',
                'prefix' => 'SJ',
                'columns' => ['entry_date','client_name','ref_page','tin','address','project_id','project_name','entry_type','sales_invoice_no','debit','credit','sundry_account_title','sundry_debit','sundry_credit','remarks'],
            ],
            'cash_disbursements' => [
                'label' => 'Cash Disbursements Journal',
                'short' => 'Cash Disbursements',
                'entry_label' => 'New Cash Disbursements Entry',
                'prefix' => 'CDJ',
                'columns' => ['entry_date','supplier','ref_page','voucher_no','tin','vat_nvat','goods_service','address','description','project_id','project_name','reference_no','debit','credit','sundry_account_title','sundry_debit','sundry_credit','remarks'],
            ],
        ];
    }
}

if (!function_exists('mb_accounting_journal_type')) {
    function mb_accounting_journal_type(?string $type): string {
        $type = strtolower(trim((string)$type));
        return array_key_exists($type, mb_accounting_journal_types()) ? $type : 'general';
    }
}

if (!function_exists('mb_accounting_journal_config')) {
    function mb_accounting_journal_config(?string $type): array {
        $types = mb_accounting_journal_types();
        return $types[mb_accounting_journal_type($type)];
    }
}

if (!function_exists('mb_ensure_accounting_journal_tables')) {
    function mb_ensure_accounting_journal_tables(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS accounting_journal_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            journal_type VARCHAR(40) NOT NULL,
            user_id INT NULL,
            entry_date DATE NOT NULL,
            journal_no VARCHAR(60) NULL,
            particulars TEXT NULL,
            ref_page VARCHAR(80) NULL,
            jv_no VARCHAR(120) NULL,
            client_name VARCHAR(180) NULL,
            supplier VARCHAR(180) NULL,
            party_name VARCHAR(180) NULL,
            invoice_no VARCHAR(120) NULL,
            sales_invoice_no VARCHAR(120) NULL,
            voucher_no VARCHAR(120) NULL,
            reference_no VARCHAR(120) NULL,
            tin VARCHAR(80) NULL,
            vat_nvat VARCHAR(40) NULL,
            goods_service VARCHAR(80) NULL,
            address VARCHAR(255) NULL,
            project_id VARCHAR(120) NULL,
            entry_type VARCHAR(120) NULL,
            account_title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            project_name VARCHAR(180) NULL,
            payment_method VARCHAR(80) NULL,
            debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            cash_in DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            cash_out DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            sundry_account_title VARCHAR(180) NULL,
            sundry_debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            sundry_credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_accounting_journal_type_date (journal_type, entry_date),
            INDEX idx_accounting_journal_user (user_id),
            INDEX idx_accounting_journal_no (journal_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $columns = [
            'particulars' => 'TEXT NULL',
            'ref_page' => 'VARCHAR(80) NULL',
            'jv_no' => 'VARCHAR(120) NULL',
            'client_name' => 'VARCHAR(180) NULL',
            'supplier' => 'VARCHAR(180) NULL',
            'sales_invoice_no' => 'VARCHAR(120) NULL',
            'voucher_no' => 'VARCHAR(120) NULL',
            'tin' => 'VARCHAR(80) NULL',
            'vat_nvat' => 'VARCHAR(40) NULL',
            'goods_service' => 'VARCHAR(80) NULL',
            'address' => 'VARCHAR(255) NULL',
            'project_id' => 'VARCHAR(120) NULL',
            'entry_type' => 'VARCHAR(120) NULL',
            'sundry_account_title' => 'VARCHAR(180) NULL',
            'sundry_debit' => 'DECIMAL(15,2) NOT NULL DEFAULT 0.00',
            'sundry_credit' => 'DECIMAL(15,2) NOT NULL DEFAULT 0.00',
        ];
        foreach ($columns as $name => $definition) {
            if (!mb_accounting_column_exists($pdo, 'accounting_journal_entries', $name)) {
                $pdo->exec("ALTER TABLE accounting_journal_entries ADD COLUMN `$name` $definition");
            }
        }
    }
}

if (!function_exists('mb_accounting_column_exists')) {
    function mb_accounting_column_exists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('mb_accounting_journal_field_labels')) {
    function mb_accounting_journal_field_labels(): array {
        return [
            'entry_date' => 'Date',
            'particulars' => 'Particulars',
            'ref_page' => 'Ref. Page',
            'jv_no' => 'JV No.',
            'client_name' => 'Client Name',
            'supplier' => 'Supplier',
            'tin' => 'TIN',
            'vat_nvat' => 'VAT/ NVAT',
            'goods_service' => 'Goods/ Service',
            'address' => 'Address',
            'project_id' => 'Project ID',
            'project_name' => 'Project Name',
            'entry_type' => 'Type',
            'sales_invoice_no' => 'Sales Invoice No.',
            'voucher_no' => 'Voucher No.',
            'reference_no' => 'Reference',
            'description' => 'Description',
            'debit' => 'Debit',
            'credit' => 'Credit',
            'sundry_account_title' => 'Sundry - Account Title',
            'sundry_debit' => 'Sundry - Debit',
            'sundry_credit' => 'Sundry - Credit',
            'remarks' => 'Remarks',
        ];
    }
}

if (!function_exists('mb_accounting_journal_columns')) {
    function mb_accounting_journal_columns(string $type): array {
        $config = mb_accounting_journal_config($type);
        return $config['columns'];
    }
}

if (!function_exists('mb_money_fmt')) {
    function mb_money_fmt($value): string {
        return number_format((float)$value, 2);
    }
}

if (!function_exists('mb_decimal_input')) {
    function mb_decimal_input($value): float {
        return round((float)str_replace([',', ' '], '', (string)$value), 2);
    }
}

if (!function_exists('mb_next_accounting_journal_no')) {
    function mb_next_accounting_journal_no(PDO $pdo, string $type): string {
        $config = mb_accounting_journal_config($type);
        return $config['prefix'] . '-' . date('Ymd-His');
    }
}

if (!function_exists('mb_journal_entry_url')) {
    function mb_journal_entry_url(string $type, ?int $id = null): string {
        $url = 'accounting_journal_entry.php?type=' . urlencode(mb_accounting_journal_type($type));
        if ($id) $url .= '&id=' . (int)$id;
        return $url;
    }
}

if (!function_exists('mb_journal_list_url')) {
    function mb_journal_list_url(string $type): string {
        return 'accounting_journals.php?type=' . urlencode(mb_accounting_journal_type($type));
    }
}
