<?php
require "config.php";
require "helpers.php";

header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// still require login so strangers can't access your data
$uid = (int)($_SESSION["user_id"] ?? 0);
if (!$uid) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$type = $_GET["type"] ?? "";
$qRaw = trim($_GET["q"] ?? "");
$q    = mb_strtolower($qRaw, 'UTF-8');

// Limits: small when empty, bigger when typing (find old data)
$LIMIT_EMPTY = 80;
$LIMIT_TYPED = 300;
$limit = ($q !== '') ? $LIMIT_TYPED : $LIMIT_EMPTY;

try {

    // Helper: distinct list with prefix-first then contains
    $fetchDistinct = function(string $col) use ($pdo, $q, $limit) {
        if ($q === '') {
            $stmt = $pdo->prepare("
                SELECT p.$col
                FROM purchase_entries p
                WHERE p.$col IS NOT NULL AND p.$col <> ''
                GROUP BY p.$col
                ORDER BY MAX(p.id) DESC
                LIMIT $limit
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Prefix first
        $stmt1 = $pdo->prepare("
            SELECT p.$col
            FROM purchase_entries p
            WHERE p.$col IS NOT NULL AND p.$col <> ''
              AND LOWER(p.$col) LIKE :pref
            GROUP BY p.$col
            ORDER BY MAX(p.id) DESC
            LIMIT $limit
        ");
        $stmt1->execute([':pref' => $q . '%']);
        $a = $stmt1->fetchAll(PDO::FETCH_COLUMN);

        $remaining = max(0, $limit - count($a));
        if ($remaining <= 0) return $a;

        // Contains fallback
        $stmt2 = $pdo->prepare("
            SELECT p.$col
            FROM purchase_entries p
            WHERE p.$col IS NOT NULL AND p.$col <> ''
              AND LOWER(p.$col) LIKE :any
              AND LOWER(p.$col) NOT LIKE :pref
            GROUP BY p.$col
            ORDER BY MAX(p.id) DESC
            LIMIT $remaining
        ");
        $stmt2->execute([
            ':any'  => '%' . $q . '%',
            ':pref' => $q . '%'
        ]);
        $b = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_unique(array_merge($a, $b)));
    };

    if ($type === "supplier") {
        echo json_encode($fetchDistinct('supplier'));
        exit;
    }

    if ($type === "project") {
        echo json_encode($fetchDistinct('project_name'));
        exit;
    }

    if ($type === "address") {
        echo json_encode($fetchDistinct('address'));
        exit;
    }

    if ($type === "address_for_supplier") {
        $supplierRaw = trim($_GET["supplier"] ?? "");
        $supplier = mb_strtolower($supplierRaw, 'UTF-8');
        if ($supplier === "") { echo json_encode([""]); exit; }

        // Exact supplier first (case-insensitive)
        $stmt = $pdo->prepare("
            SELECT p.address
            FROM purchase_entries p
            WHERE p.address IS NOT NULL AND p.address <> ''
              AND LOWER(p.supplier) = :sup
            ORDER BY p.id DESC
            LIMIT 1
        ");
        $stmt->execute([':sup' => $supplier]);
        $addr = $stmt->fetchColumn();

        // Fallback: contains supplier
        if (!$addr) {
            $stmt = $pdo->prepare("
                SELECT p.address
                FROM purchase_entries p
                WHERE p.address IS NOT NULL AND p.address <> ''
                  AND LOWER(p.supplier) LIKE :sup
                ORDER BY p.id DESC
                LIMIT 1
            ");
            $stmt->execute([':sup' => '%'.$supplier.'%']);
            $addr = $stmt->fetchColumn();
        }

        echo json_encode([$addr ?: ""]);
        exit;
    }

    if ($type === "vat_mode_for_supplier") {
        $supplierRaw = trim($_GET["supplier"] ?? "");
        $supplier = mb_strtolower($supplierRaw, 'UTF-8');
        if ($supplier === "") { echo json_encode([""]); exit; }

        // Exact supplier first (case-insensitive)
        $stmt = $pdo->prepare("
            SELECT p.vat_nvat
            FROM purchase_entries p
            WHERE p.vat_nvat IS NOT NULL AND p.vat_nvat <> ''
              AND LOWER(p.supplier) = :sup
            ORDER BY p.id DESC
            LIMIT 1
        ");
        $stmt->execute([':sup' => $supplier]);
        $mode = $stmt->fetchColumn();

        // Fallback: contains supplier
        if (!$mode) {
            $stmt = $pdo->prepare("
                SELECT p.vat_nvat
                FROM purchase_entries p
                WHERE p.vat_nvat IS NOT NULL AND p.vat_nvat <> ''
                  AND LOWER(p.supplier) LIKE :sup
                ORDER BY p.id DESC
                LIMIT 1
            ");
            $stmt->execute([':sup' => '%'.$supplier.'%']);
            $mode = $stmt->fetchColumn();
        }

        $mode = (strcasecmp((string)$mode, 'nonvat') === 0) ? 'NonVAT' : ((strcasecmp((string)$mode, 'vat') === 0) ? 'VAT' : '');
        echo json_encode([$mode]);
        exit;
    }

    if ($type === "tin") {
        $supplierRaw = trim($_GET["supplier"] ?? "");
        $addressRaw  = trim($_GET["address"] ?? "");
        $supplier    = mb_strtolower($supplierRaw, 'UTF-8');
        $address     = mb_strtolower($addressRaw, 'UTF-8');

        if ($supplier === "" || $address === "") { echo json_encode([""]); exit; }

        // Exact match first
        $stmt = $pdo->prepare("
            SELECT p.tin
            FROM purchase_entries p
            WHERE p.tin IS NOT NULL AND p.tin <> ''
              AND LOWER(p.supplier)=:sup
              AND LOWER(p.address)=:addr
            ORDER BY p.id DESC
            LIMIT 1
        ");
        $stmt->execute([':sup'=>$supplier, ':addr'=>$address]);
        $tin = $stmt->fetchColumn();

        // Fallback: contains match
        if (!$tin) {
            $stmt = $pdo->prepare("
                SELECT p.tin
                FROM purchase_entries p
                WHERE p.tin IS NOT NULL AND p.tin <> ''
                  AND LOWER(p.supplier) LIKE :sup
                  AND LOWER(p.address) LIKE :addr
                ORDER BY p.id DESC
                LIMIT 1
            ");
            $stmt->execute([':sup'=>'%'.$supplier.'%', ':addr'=>'%'.$address.'%']);
            $tin = $stmt->fetchColumn();
        }

        echo json_encode([$tin ?: ""]);
        exit;
    }

    echo json_encode([]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
