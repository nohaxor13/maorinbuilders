<?php
// journal_api.php
declare(strict_types=1);

require "config.php";
require "helpers.php";
redirect_if_not_logged_in();

header('Content-Type: application/json; charset=utf-8');

try {
  // --- current user (for is_owner only) ---
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if (!$uid) throw new Exception('Unauthorized');

  // --- inputs ---
  $search   = trim($_GET['search'] ?? '');
  $sort     = $_GET['sort'] ?? 'date_desc';
  $pageReq  = (int)($_GET['page'] ?? 1);
  $dateFrom = trim($_GET['date_from'] ?? '');
  $dateTo   = trim($_GET['date_to'] ?? '');

  // ✅ NO LIMIT by default:
  // - If ?limit is NOT provided OR <= 0 => return ALL rows (no LIMIT/OFFSET)
  // - If ?limit is provided and > 0 => paginate using LIMIT/OFFSET
  $limitReq  = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
  $usePaging = ($limitReq > 0);

  $page  = max(1, $pageReq);
  $limit = $usePaging ? $limitReq : 0;

  // validate dates (YYYY-MM-DD)
  $reDate = '/^\d{4}-\d{2}-\d{2}$/';
  if ($dateFrom !== '' && !preg_match($reDate, $dateFrom)) $dateFrom = '';
  if ($dateTo   !== '' && !preg_match($reDate, $dateTo))   $dateTo   = '';

  // whitelist sorting (use aliases with table p)
  $sortMap = [
    'date_desc'        => 'p.date DESC, p.id DESC',
    'date_asc'         => 'p.date ASC, p.id ASC',
    'supplier_asc'     => 'p.supplier ASC, p.date DESC',
    'supplier_desc'    => 'p.supplier DESC, p.date DESC',
    'ref_page_asc'     => 'p.ref_page ASC, p.date DESC',
    'ref_page_desc'    => 'p.ref_page DESC, p.date DESC',
    'tin_asc'          => 'p.tin ASC, p.date DESC',
    'tin_desc'         => 'p.tin DESC, p.date DESC',
    'vat_nvat_asc'     => 'p.vat_nvat ASC, p.date DESC',
    'vat_nvat_desc'    => 'p.vat_nvat DESC, p.date DESC',
    'address_asc'      => 'p.address ASC, p.date DESC',
    'address_desc'     => 'p.address DESC, p.date DESC',
    'description_asc'  => 'p.description ASC, p.date DESC',
    'description_desc' => 'p.description DESC, p.date DESC',
    'project_name_asc' => 'p.project_name ASC, p.date DESC',
    'project_name_desc'=> 'p.project_name DESC, p.date DESC',
    'input_vat_desc'   => 'p.input_vat DESC, p.date DESC',
    'input_vat_asc'    => 'p.input_vat ASC, p.date DESC',
    'vatable_desc'     => 'p.vatable DESC, p.date DESC',
    'vatable_asc'      => 'p.vatable ASC, p.date DESC',
    'non_vat_desc'     => 'p.non_vat DESC, p.date DESC',
    'non_vat_asc'      => 'p.non_vat ASC, p.date DESC',
    'total_desc'       => 'p.total DESC, p.date DESC',
    'total_asc'        => 'p.total ASC, p.date DESC',
    'cash_desc'        => 'p.cash DESC, p.date DESC',
    'cash_asc'         => 'p.cash ASC, p.date DESC',
    'debit_desc'       => 'p.debit DESC, p.date DESC',
    'debit_asc'        => 'p.debit ASC, p.date DESC',
    'credit_desc'      => 'p.credit DESC, p.date DESC',
    'credit_asc'       => 'p.credit ASC, p.date DESC',
    'entered_by_asc'   => 'u.name ASC, p.date DESC',
    'entered_by_desc'  => 'u.name DESC, p.date DESC',
    'remarks_asc'      => 'p.remarks ASC, p.date DESC',
    'remarks_desc'     => 'p.remarks DESC, p.date DESC',
  ];
  $orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

  // --- WHERE conditions ---
  $where  = '1=1';
  $params = [];

  if ($search !== '') {
    $where .= " AND (
        p.supplier      LIKE :s OR
        p.description   LIKE :s OR
        p.remarks       LIKE :s OR
        p.category      LIKE :s OR
        p.project_name  LIKE :s OR
        p.reference     LIKE :s OR
        p.address       LIKE :s OR
        p.tin           LIKE :s OR
        p.vat_nvat      LIKE :s OR
        p.account_title LIKE :s OR
        u.name          LIKE :s
      )";
    $params[':s'] = '%'.$search.'%';
  }

  if ($dateFrom !== '' && $dateTo !== '') {
    $where .= ' AND p.date BETWEEN :df AND :dt';
    $params[':df'] = $dateFrom;
    $params[':dt'] = $dateTo;
  } elseif ($dateFrom !== '') {
    $where .= ' AND p.date >= :df';
    $params[':df'] = $dateFrom;
  } elseif ($dateTo !== '') {
    $where .= ' AND p.date <= :dt';
    $params[':dt'] = $dateTo;
  }

  // count total (still useful for UI)
  $sqlCount = "SELECT COUNT(*)
                 FROM purchase_entries p
                 LEFT JOIN users u ON u.id = p.user_id
                WHERE $where";
  $stmt = $pdo->prepare($sqlCount);
  $stmt->execute($params);
  $total = (int)$stmt->fetchColumn();

  // paging values
  if ($usePaging) {
    $pages = max(1, (int)ceil($total / $limit));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $limit;
  } else {
    $pages  = 1;
    $page   = 1;
    $offset = 0;
  }

  // fetch data (✅ no LIMIT unless paging is enabled)
  $sql = "SELECT
            p.id,
            p.user_id AS owner_id,
            p.date,
            p.supplier,
            p.ref_page,
            p.tin,
            p.vat_nvat,
            p.address,
            p.category,
            p.description,
            p.project_name,
            p.reference,
            p.input_vat,
            p.vatable,
            p.non_vat,
            p.total,
            p.freight_handling,
            p.cash,
            p.account_title,
            p.debit,
            p.credit,
            p.remarks,
            u.name AS entered_by
          FROM purchase_entries p
          LEFT JOIN users u ON u.id = p.user_id
          WHERE $where
          ORDER BY $orderBy";

  if ($usePaging) {
    $sql .= " LIMIT :limit OFFSET :offset";
  }

  $stmt = $pdo->prepare($sql);

  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }

  if ($usePaging) {
    $stmt->bindValue(':limit',  $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  }

  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // shape output: add is_owner; hide owner_id
  $out = [];
  foreach ($rows as $r) {
    $r['is_owner'] = ((int)($r['owner_id'] ?? 0) === $uid);
    unset($r['owner_id']);
    if (!isset($r['entered_by']) || $r['entered_by'] === null) {
      $r['entered_by'] = '';
    }
    $out[] = $r;
  }

  echo json_encode([
    'ok'        => true,
    'data'      => $out,
    'total'     => $total,
    'page'      => $page,
    'pages'     => $pages,
    'limit'     => $limit,
    'usePaging' => $usePaging
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
