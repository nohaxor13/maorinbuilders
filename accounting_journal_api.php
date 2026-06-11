<?php
declare(strict_types=1);

require "config.php";
require "helpers.php";
require "accounting_journal_common.php";

redirect_if_not_logged_in();
require_permission($pdo, 'view_journal');
mb_ensure_accounting_journal_tables($pdo);

header('Content-Type: application/json; charset=utf-8');

try {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid) throw new RuntimeException('Unauthorized');

    $action = $_GET['action'] ?? 'list';
    $type = mb_accounting_journal_type($_GET['type'] ?? $_POST['type'] ?? 'general');

    if ($action === 'delete') {
        require_permission($pdo, 'delete_journal');
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Invalid id');

        $stmt = $pdo->prepare("DELETE FROM accounting_journal_entries WHERE id=? AND journal_type=?");
        $stmt->execute([$id, $type]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $sort = (string)($_GET['sort'] ?? 'date_desc');
    $pageReq = max(1, (int)($_GET['page'] ?? 1));
    $limitReq = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
    $usePaging = $limitReq > 0;
    $limit = $usePaging ? min(200, max(5, $limitReq)) : 0;

    $sortMap = [
        'date_desc' => 'entry_date DESC, id DESC',
        'date_asc' => 'entry_date ASC, id ASC',
        'journal_no_asc' => 'journal_no ASC, id ASC',
        'journal_no_desc' => 'journal_no DESC, id DESC',
        'party_asc' => 'party_name ASC, entry_date DESC',
        'party_desc' => 'party_name DESC, entry_date DESC',
        'account_asc' => 'account_title ASC, entry_date DESC',
        'account_desc' => 'account_title DESC, entry_date DESC',
        'debit_desc' => 'debit DESC, entry_date DESC',
        'debit_asc' => 'debit ASC, entry_date DESC',
        'credit_desc' => 'credit DESC, entry_date DESC',
        'credit_asc' => 'credit ASC, entry_date DESC',
        'sundry_debit_desc' => 'sundry_debit DESC, entry_date DESC',
        'sundry_debit_asc' => 'sundry_debit ASC, entry_date DESC',
        'sundry_credit_desc' => 'sundry_credit DESC, entry_date DESC',
        'sundry_credit_asc' => 'sundry_credit ASC, entry_date DESC',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

    $where = 'journal_type = :type';
    $params = [':type' => $type];

    if ($search !== '') {
        $where .= " AND (
            journal_no LIKE :s OR particulars LIKE :s OR ref_page LIKE :s OR jv_no LIKE :s OR client_name LIKE :s OR
            supplier LIKE :s OR party_name LIKE :s OR invoice_no LIKE :s OR sales_invoice_no LIKE :s OR voucher_no LIKE :s OR
            reference_no LIKE :s OR tin LIKE :s OR vat_nvat LIKE :s OR goods_service LIKE :s OR address LIKE :s OR
            project_id LIKE :s OR project_name LIKE :s OR entry_type LIKE :s OR account_title LIKE :s OR description LIKE :s OR
            sundry_account_title LIKE :s OR remarks LIKE :s
        )";
        $params[':s'] = '%' . $search . '%';
    }

    $reDate = '/^\d{4}-\d{2}-\d{2}$/';
    if ($dateFrom !== '' && preg_match($reDate, $dateFrom)) {
        $where .= ' AND entry_date >= :df';
        $params[':df'] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match($reDate, $dateTo)) {
        $where .= ' AND entry_date <= :dt';
        $params[':dt'] = $dateTo;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM accounting_journal_entries WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalsStmt = $pdo->prepare("SELECT
        COALESCE(SUM(debit),0) debit,
        COALESCE(SUM(credit),0) credit,
        COALESCE(SUM(cash_in),0) cash_in,
        COALESCE(SUM(cash_out),0) cash_out,
        COALESCE(SUM(sundry_debit),0) sundry_debit,
        COALESCE(SUM(sundry_credit),0) sundry_credit
        FROM accounting_journal_entries WHERE $where");
    $totalsStmt->execute($params);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($usePaging) {
        $pages = max(1, (int)ceil($total / $limit));
        $page = min($pageReq, $pages);
        $offset = ($page - 1) * $limit;
    } else {
        $pages = 1;
        $page = 1;
        $offset = 0;
    }

    $sql = "SELECT e.*, u.name AS entered_by
            FROM accounting_journal_entries e
            LEFT JOIN users u ON u.id = e.user_id
            WHERE $where
            ORDER BY $orderBy";
    if ($usePaging) $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    if ($usePaging) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'data' => $rows,
        'total' => $total,
        'totals' => $totals,
        'page' => $page,
        'pages' => $pages,
        'limit' => $limit,
        'usePaging' => $usePaging,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
