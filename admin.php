<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);

ensure_site_settings_table($pdo);
ensure_table_user_roles($pdo);
ensure_roles_permissions_tables($pdo);
ensure_staff_profiles_table($pdo);
ensure_staff_activity_log_table($pdo);
ensure_content_catalog_tables($pdo);

$currentAdminId = (int)($_SESSION['user_id'] ?? 0);
$flashError = (string)($_SESSION['admin_flash_error'] ?? '');
$flashSuccess = (string)($_SESSION['admin_flash_success'] ?? '');
unset($_SESSION['admin_flash_error'], $_SESSION['admin_flash_success']);
$maintenanceSaved = false;

$projectUploadDir = __DIR__ . '/storage/uploads/projects';
if (!is_dir($projectUploadDir)) {
  @mkdir($projectUploadDir, 0775, true);
}

if (!function_exists('admin_save_project_image')) {
  function admin_save_project_image(array $file, string $projectUploadDir, string $prefix): ?string {
    if (empty($file['name']) || !is_uploaded_file($file['tmp_name'])) {
      return null;
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
      throw new RuntimeException('Unsupported image format. Use JPG, PNG, WEBP, or GIF.');
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
      throw new RuntimeException('Image too large. Max size is 8MB.');
    }
    $safePrefix = preg_replace('/[^a-z0-9_\-]+/i', '_', $prefix) ?: 'project';
    $fname = $safePrefix . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
    $dest = $projectUploadDir . '/' . $fname;
    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
      throw new RuntimeException('Upload failed. Please try again.');
    }
    return 'storage/uploads/projects/' . $fname;
  }
}

if (!function_exists('admin_delete_project_image_file')) {
  function admin_delete_project_image_file(?string $path): void {
    if (!$path) return;
    $abs = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($abs)) {
      @unlink($abs);
    }
  }
}

if (!function_exists('admin_normalize_project_materials')) {
  function admin_normalize_project_materials(string $text): array {
    $text = trim($text);
    if ($text === '') {
      return [];
    }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
      return array_values(array_filter(array_map(static fn($item) => trim((string)$item), $decoded), static fn($item) => $item !== ''));
    }
    return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $text) ?: [])));
  }
}

if (!function_exists('admin_project_materials_text')) {
  function admin_project_materials_text($materials): string {
    if (is_string($materials) && $materials !== '') {
      $decoded = json_decode($materials, true);
      if (is_array($decoded)) {
        return implode("\n", array_values(array_filter(array_map(static fn($item) => trim((string)$item), $decoded), static fn($item) => $item !== '')));
      }
      return implode("\n", array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $materials) ?: []))));
    }
    if (is_array($materials)) {
      return implode("\n", array_values(array_filter(array_map(static fn($item) => trim((string)$item), $materials), static fn($item) => $item !== '')));
    }
    return '';
  }
}

if (!function_exists('admin_project_media_url')) {
  function admin_project_media_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
      return '';
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $root = rtrim(dirname($script), '/');
    if ($root === '.' || $root === '/') {
      $root = '';
    }
    return $root . '/' . ltrim($path, '/');
  }
}

if (!function_exists('admin_project_payload_json')) {
  function admin_project_payload_json(array $project): string {
    $payload = [
      'id' => (int)($project['id'] ?? 0),
      'originalSlug' => (string)($project['slug'] ?? ''),
      'slug' => (string)($project['slug'] ?? ''),
      'title' => (string)($project['title'] ?? ''),
      'location' => (string)($project['location'] ?? ''),
      'year' => (string)($project['year'] ?? ''),
      'type' => (string)($project['type'] ?? ''),
      'status' => (string)($project['status'] ?? 'Ongoing'),
      'coverUrl' => admin_project_media_url((string)($project['cover'] ?? '')),
      'beforeUrl' => admin_project_media_url((string)($project['before_image'] ?? '')),
      'afterUrl' => admin_project_media_url((string)($project['after_image'] ?? '')),
      'materialsText' => admin_project_materials_text($project['materials'] ?? ''),
      'summary' => (string)($project['summary'] ?? ''),
    ];
    return htmlspecialchars(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('admin_set_flash_and_redirect')) {
  function admin_set_flash_and_redirect(string $type, string $message, string $tab = '#tab-overview'): void {
    $_SESSION[$type === 'error' ? 'admin_flash_error' : 'admin_flash_success'] = $message;
    header('Location: admin.php' . $tab);
    exit;
  }
}

if (!function_exists('admin_find_project')) {
  function admin_find_project(PDO $pdo, int $id, string $slug): ?array {
    if ($id > 0) {
      $st = $pdo->prepare("SELECT id, slug, cover, before_image, after_image, gallery FROM website_projects WHERE id = ? LIMIT 1");
      $st->execute([$id]);
      $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
      if ($row) {
        return $row;
      }
    }
    if ($slug !== '') {
      $st = $pdo->prepare("SELECT id, slug, cover, before_image, after_image, gallery FROM website_projects WHERE slug = ? LIMIT 1");
      $st->execute([$slug]);
      return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return null;
  }
}

if (!function_exists('admin_sync_project_gallery')) {
  function admin_sync_project_gallery(PDO $pdo, int $projectId): array {
    if ($projectId <= 0) {
      return [];
    }
    $mediaSt = $pdo->prepare("SELECT id, path FROM website_project_media WHERE project_id = ? AND media_type = 'gallery' ORDER BY created_at DESC, id DESC");
    $mediaSt->execute([$projectId]);
    $rows = $mediaSt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $seen = [];
    $paths = [];
    $duplicateIds = [];
    foreach ($rows as $row) {
      $path = trim((string)($row['path'] ?? ''));
      if ($path === '') {
        continue;
      }
      if (isset($seen[$path])) {
        $duplicateIds[] = (int)$row['id'];
        continue;
      }
      $seen[$path] = true;
      $paths[] = $path;
    }
    if ($duplicateIds) {
      $delete = $pdo->prepare("DELETE FROM website_project_media WHERE id = ?");
      foreach ($duplicateIds as $duplicateId) {
        $delete->execute([$duplicateId]);
      }
    }
    $galleryJson = $paths ? json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $up = $pdo->prepare("UPDATE website_projects SET gallery = ? WHERE id = ?");
    $up->execute([$galleryJson, $projectId]);
    return $paths;
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_maintenance') {
      $maintenanceMode = !empty($_POST['maintenance_mode']) ? '1' : '0';
      $maintenanceMessage = trim((string)($_POST['maintenance_message'] ?? ''));
      if ($maintenanceMessage === '') {
        $maintenanceMessage = 'We are currently doing maintenance. Please check back later.';
      }
      site_setting_set($pdo, 'maintenance_mode', $maintenanceMode);
      site_setting_set($pdo, 'maintenance_message', $maintenanceMessage);
      if (isset($_POST['maintenance_retry_after'])) {
        $retryAfter = (string)max(60, (int)$_POST['maintenance_retry_after']);
        site_setting_set($pdo, 'maintenance_retry_after', $retryAfter);
      }
      $maintenanceSaved = true;
      $flashSuccess = 'Maintenance settings updated.';
    } elseif ($action === 'create_role') {
      $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
      $name = trim((string)($_POST['name'] ?? ''));
      $permissions = array_values(array_filter(array_map('trim', (array)($_POST['permissions'] ?? []))));
      if ($slug === '' || $name === '') {
        throw new RuntimeException('Role slug and name are required.');
      }
      if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
        throw new RuntimeException('Role slug may only contain letters, numbers, dashes, and underscores.');
      }
      if (in_array($slug, ['admin', 'staff', 'accounting', 'warehouse'], true)) {
        throw new RuntimeException('Use a unique slug for custom roles.');
      }
      $pdo->beginTransaction();
      $st = $pdo->prepare("INSERT INTO roles (slug, name, is_system) VALUES (?, ?, 0)");
      $st->execute([$slug, $name]);
      $permStmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, permission_key) VALUES (?, ?)");
      foreach ($permissions as $permission) {
        if (array_key_exists($permission, permission_catalog())) {
          $permStmt->execute([$slug, $permission]);
        }
      }
      $pdo->commit();
      $flashSuccess = 'Role created.';
    } elseif ($action === 'update_role') {
      $slug = trim((string)($_POST['slug'] ?? ''));
      $name = trim((string)($_POST['name'] ?? ''));
      $permissions = array_values(array_filter(array_map('trim', (array)($_POST['permissions'] ?? []))));
      if ($slug === '' || $name === '') {
        throw new RuntimeException('Role slug and name are required.');
      }
      $roleStmt = $pdo->prepare("SELECT is_system FROM roles WHERE slug = ? LIMIT 1");
      $roleStmt->execute([$slug]);
      $isSystem = (int)$roleStmt->fetchColumn();
      if ($isSystem && in_array($slug, ['admin', 'staff', 'accounting', 'warehouse'], true) === false) {
        throw new RuntimeException('This role cannot be edited.');
      }
      $pdo->beginTransaction();
      $st = $pdo->prepare("UPDATE roles SET name = ? WHERE slug = ?");
      $st->execute([$name, $slug]);
      $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_slug = ?");
      $del->execute([$slug]);
      $permStmt = $pdo->prepare("INSERT INTO role_permissions (role_slug, permission_key) VALUES (?, ?)");
      foreach ($permissions as $permission) {
        if (array_key_exists($permission, permission_catalog())) {
          $permStmt->execute([$slug, $permission]);
        }
      }
      $pdo->commit();
      $flashSuccess = 'Role updated.';
    } elseif ($action === 'delete_role') {
      $slug = trim((string)($_POST['slug'] ?? ''));
      if (in_array($slug, ['admin', 'staff', 'accounting', 'warehouse'], true)) {
        throw new RuntimeException('Core roles cannot be deleted.');
      }
      $pdo->beginTransaction();
      $pdo->prepare("DELETE FROM role_permissions WHERE role_slug = ?")->execute([$slug]);
      $pdo->prepare("DELETE FROM roles WHERE slug = ?")->execute([$slug]);
      $pdo->prepare("UPDATE user_roles SET role = 'staff' WHERE role = ?")->execute([$slug]);
      $pdo->commit();
      $flashSuccess = 'Role deleted.';
    } elseif ($action === 'create_staff') {
      $name = trim((string)($_POST['name'] ?? ''));
      $email = strtolower(trim((string)($_POST['email'] ?? '')));
      $password = (string)($_POST['password'] ?? '');
      $role = trim((string)($_POST['role'] ?? 'staff'));
      $jobTitle = trim((string)($_POST['job_title'] ?? ''));
      $department = trim((string)($_POST['department'] ?? ''));
      $phone = trim((string)($_POST['phone'] ?? ''));
      $address = trim((string)($_POST['address'] ?? ''));
      $bio = trim((string)($_POST['bio'] ?? ''));

      if ($name === '' || $email === '' || $password === '') {
        throw new RuntimeException('Name, email, and password are required.');
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
      }
      $allowedRoleStmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE slug = ?");
      $allowedRoleStmt->execute([$role]);
      if ((int)$allowedRoleStmt->fetchColumn() === 0) {
        $role = 'staff';
      }

      $pdo->beginTransaction();
      $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
      $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
      $userId = (int)$pdo->lastInsertId();

      $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)");
      $roleStmt->execute([$userId, $role]);

      $profileStmt = $pdo->prepare(
        "INSERT INTO staff_profiles (user_id, job_title, department, phone, address, bio)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           job_title = VALUES(job_title),
           department = VALUES(department),
           phone = VALUES(phone),
           address = VALUES(address),
           bio = VALUES(bio)"
      );
      $profileStmt->execute([$userId, $jobTitle ?: null, $department ?: null, $phone ?: null, $address ?: null, $bio ?: null]);

      log_staff_activity($pdo, $userId, 'account_created', 'Staff account created by admin.', $currentAdminId ?: null);
      log_staff_activity($pdo, $userId, 'role_assigned', 'Initial role set to ' . $role . '.', $currentAdminId ?: null);

      $pdo->commit();
      $flashSuccess = 'Staff account created.';
    } elseif ($action === 'update_role') {
      $userId = (int)($_POST['user_id'] ?? 0);
      $role = trim((string)($_POST['role'] ?? 'staff'));
      if ($userId <= 0) {
        throw new RuntimeException('Invalid staff member.');
      }
      $allowedRoleStmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE slug = ?");
      $allowedRoleStmt->execute([$role]);
      if ((int)$allowedRoleStmt->fetchColumn() === 0) {
        throw new RuntimeException('Invalid role selected.');
      }
      $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)");
      $stmt->execute([$userId, $role]);
      log_staff_activity($pdo, $userId, 'role_updated', 'Role changed to ' . $role . '.', $currentAdminId ?: null);
      $flashSuccess = 'Role updated.';
    } elseif ($action === 'save_profile') {
      $userId = (int)($_POST['user_id'] ?? 0);
      if ($userId <= 0) {
        throw new RuntimeException('Invalid staff member.');
      }
      $jobTitle = trim((string)($_POST['job_title'] ?? ''));
      $department = trim((string)($_POST['department'] ?? ''));
      $phone = trim((string)($_POST['phone'] ?? ''));
      $address = trim((string)($_POST['address'] ?? ''));
      $bio = trim((string)($_POST['bio'] ?? ''));
      $stmt = $pdo->prepare(
        "INSERT INTO staff_profiles (user_id, job_title, department, phone, address, bio)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           job_title = VALUES(job_title),
           department = VALUES(department),
           phone = VALUES(phone),
           address = VALUES(address),
           bio = VALUES(bio)"
      );
      $stmt->execute([$userId, $jobTitle ?: null, $department ?: null, $phone ?: null, $address ?: null, $bio ?: null]);
      log_staff_activity($pdo, $userId, 'profile_updated', 'Staff profile updated by admin.', $currentAdminId ?: null);
      $flashSuccess = 'Profile saved.';
    } elseif ($action === 'update_staff') {
      $userId = (int)($_POST['user_id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));
      $email = strtolower(trim((string)($_POST['email'] ?? '')));
      $role = trim((string)($_POST['role'] ?? 'staff'));
      $jobTitle = trim((string)($_POST['job_title'] ?? ''));
      $department = trim((string)($_POST['department'] ?? ''));
      $phone = trim((string)($_POST['phone'] ?? ''));
      $address = trim((string)($_POST['address'] ?? ''));
      $bio = trim((string)($_POST['bio'] ?? ''));
      if ($userId <= 0 || $name === '' || $email === '') {
        throw new RuntimeException('Staff name and email are required.');
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
      }
      $allowedRoleStmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE slug = ?");
      $allowedRoleStmt->execute([$role]);
      if ((int)$allowedRoleStmt->fetchColumn() === 0) {
        throw new RuntimeException('Invalid role selected.');
      }
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
      $stmt->execute([$name, $email, $userId]);
      $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)");
      $roleStmt->execute([$userId, $role]);
      $profileStmt = $pdo->prepare(
        "INSERT INTO staff_profiles (user_id, job_title, department, phone, address, bio)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           job_title = VALUES(job_title),
           department = VALUES(department),
           phone = VALUES(phone),
           address = VALUES(address),
           bio = VALUES(bio)"
      );
      $profileStmt->execute([$userId, $jobTitle ?: null, $department ?: null, $phone ?: null, $address ?: null, $bio ?: null]);
      log_staff_activity($pdo, $userId, 'staff_updated', 'Staff record updated by admin.', $currentAdminId ?: null);
      $pdo->commit();
      $flashSuccess = 'Staff details updated.';
    } elseif ($action === 'reset_password') {
      $userId = (int)($_POST['user_id'] ?? 0);
      $password = (string)($_POST['password'] ?? '');
      if ($userId <= 0 || $password === '') {
        throw new RuntimeException('A new password is required.');
      }
      $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
      $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
      log_staff_activity($pdo, $userId, 'password_reset', 'Password reset by admin.', $currentAdminId ?: null);
      $flashSuccess = 'Password reset.';
    } elseif ($action === 'save_service') {
      $id = (int)($_POST['id'] ?? 0);
      $slug = trim((string)($_POST['slug'] ?? ''));
      $name = trim((string)($_POST['name'] ?? ''));
      $desc = trim((string)($_POST['desc_text'] ?? ''));
      $href = trim((string)($_POST['href'] ?? ''));
      $range = trim((string)($_POST['range_text'] ?? ''));
      $timeline = trim((string)($_POST['timeline_text'] ?? ''));
      if ($slug === '' || $name === '' || $desc === '') {
        throw new RuntimeException('Service slug, name, and description are required.');
      }
      $st = $pdo->prepare(
        "INSERT INTO website_services (id, slug, name, desc_text, href, range_text, timeline_text)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           slug = VALUES(slug),
           name = VALUES(name),
           desc_text = VALUES(desc_text),
           href = VALUES(href),
           range_text = VALUES(range_text),
           timeline_text = VALUES(timeline_text)"
      );
      $st->execute([$id ?: null, $slug, $name, $desc, $href ?: null, $range ?: null, $timeline ?: null]);
      $flashSuccess = 'Service saved.';
    } elseif ($action === 'delete_service') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $st = $pdo->prepare("DELETE FROM website_services WHERE id = ?");
        $st->execute([$id]);
        $flashSuccess = 'Service deleted.';
      }
    } elseif ($action === 'save_project') {
      $id = (int)($_POST['id'] ?? 0);
      $originalSlug = trim((string)($_POST['original_slug'] ?? ''));
      $slug = trim((string)($_POST['slug'] ?? ''));
      $titleP = trim((string)($_POST['title'] ?? ''));
      $location = trim((string)($_POST['location'] ?? ''));
      $year = trim((string)($_POST['year'] ?? ''));
      $type = trim((string)($_POST['type'] ?? ''));
      $status = trim((string)($_POST['status'] ?? 'Ongoing'));
      $cover = trim((string)($_POST['cover'] ?? ''));
      $beforeImage = trim((string)($_POST['before_image'] ?? ''));
      $afterImage = trim((string)($_POST['after_image'] ?? ''));
      $materialsText = trim((string)($_POST['materials_text'] ?? ''));
      $summary = trim((string)($_POST['summary'] ?? ''));
      if ($slug === '' || $titleP === '') {
        throw new RuntimeException('Project slug and title are required.');
      }
      $materials = admin_normalize_project_materials($materialsText);
      $lookupSlug = $originalSlug !== '' ? $originalSlug : $slug;
      $existing = admin_find_project($pdo, $id, $lookupSlug);
      $isEdit = $id > 0 || $originalSlug !== '';
      if ($isEdit && !$existing) {
        throw new RuntimeException('The project record could not be found for editing. Please refresh and try again.');
      }
      if ($existing) {
        $id = (int)$existing['id'];
      }

      $removeCover = !empty($_POST['remove_cover']);
      $removeBefore = !empty($_POST['remove_before_image']);
      $removeAfter = !empty($_POST['remove_after_image']);

      $uploadedCover = admin_save_project_image($_FILES['cover_upload'] ?? [], $projectUploadDir, $slug . '_cover');
      $uploadedBefore = admin_save_project_image($_FILES['before_upload'] ?? [], $projectUploadDir, $slug . '_before');
      $uploadedAfter = admin_save_project_image($_FILES['after_upload'] ?? [], $projectUploadDir, $slug . '_after');

      $cover = $uploadedCover ?: ($removeCover ? null : ($existing['cover'] ?? null));
      if ($uploadedCover) {
        admin_delete_project_image_file((string)($existing['cover'] ?? null));
      } elseif ($removeCover) {
        admin_delete_project_image_file((string)($existing['cover'] ?? null));
      }
      $beforeImage = $uploadedBefore ?: ($removeBefore ? null : ($existing['before_image'] ?? null));
      if ($uploadedBefore) {
        admin_delete_project_image_file((string)($existing['before_image'] ?? null));
      } elseif ($removeBefore) {
        admin_delete_project_image_file((string)($existing['before_image'] ?? null));
      }
      $afterImage = $uploadedAfter ?: ($removeAfter ? null : ($existing['after_image'] ?? null));
      if ($uploadedAfter) {
        admin_delete_project_image_file((string)($existing['after_image'] ?? null));
      } elseif ($removeAfter) {
        admin_delete_project_image_file((string)($existing['after_image'] ?? null));
      }

      $st = $pdo->prepare(
        "INSERT INTO website_projects (id, slug, title, location, year, type, status, cover, before_image, after_image, summary, materials)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           slug = VALUES(slug),
           title = VALUES(title),
           location = VALUES(location),
           year = VALUES(year),
           type = VALUES(type),
           status = VALUES(status),
           cover = VALUES(cover),
           before_image = VALUES(before_image),
           after_image = VALUES(after_image),
           summary = VALUES(summary),
           materials = VALUES(materials)"
      );
      $st->execute([
        $id ?: null,
        $slug,
        $titleP,
        $location ?: null,
        $year ?: null,
        $type ?: null,
        $status ?: 'Ongoing',
        $cover ?: null,
        $beforeImage ?: null,
        $afterImage ?: null,
        $summary ?: null,
        $materials ? json_encode($materials, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
      ]);

      $projectId = $id;
      if ($projectId <= 0) {
        $savedProject = admin_find_project($pdo, 0, $slug);
        $projectId = (int)($savedProject['id'] ?? 0);
      }
      $galleryUploads = $_FILES['gallery_uploads'] ?? null;
      $galleryChanged = false;
      if ($projectId > 0 && $galleryUploads && !empty($galleryUploads['name']) && is_array($galleryUploads['name'])) {
        $count = count($galleryUploads['name']);
        for ($i = 0; $i < $count; $i++) {
          $file = [
            'name' => $galleryUploads['name'][$i] ?? '',
            'type' => $galleryUploads['type'][$i] ?? '',
            'tmp_name' => $galleryUploads['tmp_name'][$i] ?? '',
            'error' => $galleryUploads['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $galleryUploads['size'][$i] ?? 0,
          ];
          if (empty($file['name']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            continue;
          }
          $path = admin_save_project_image($file, $projectUploadDir, $slug . '_gallery');
          $mediaInsert = $pdo->prepare("INSERT INTO website_project_media (project_id, media_type, path) VALUES (?, 'gallery', ?)");
          $mediaInsert->execute([$projectId, $path]);
          $galleryChanged = true;
        }
      }

      if ($projectId > 0 && $galleryChanged) {
        admin_sync_project_gallery($pdo, $projectId);
      }
      admin_set_flash_and_redirect('success', 'Project saved.');
    } elseif ($action === 'delete_project') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $stMedia = $pdo->prepare("SELECT cover, before_image, after_image FROM website_projects WHERE id = ? LIMIT 1");
        $stMedia->execute([$id]);
        $projMedia = $stMedia->fetch(PDO::FETCH_ASSOC) ?: [];
        admin_delete_project_image_file((string)($projMedia['cover'] ?? null));
        admin_delete_project_image_file((string)($projMedia['before_image'] ?? null));
        admin_delete_project_image_file((string)($projMedia['after_image'] ?? null));
        $st = $pdo->prepare("DELETE FROM website_projects WHERE id = ?");
        $st->execute([$id]);
        admin_set_flash_and_redirect('success', 'Project deleted.');
      }
    } elseif ($action === 'delete_project_media') {
      $mediaId = (int)($_POST['media_id'] ?? 0);
      $projectId = (int)($_POST['project_id'] ?? 0);
      if ($mediaId > 0 && $projectId > 0) {
        $st = $pdo->prepare("SELECT id, path FROM website_project_media WHERE id = ? AND project_id = ? LIMIT 1");
        $st->execute([$mediaId, $projectId]);
        $media = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($media) {
          admin_delete_project_image_file((string)$media['path']);
          $del = $pdo->prepare("DELETE FROM website_project_media WHERE id = ?");
          $del->execute([(int)$media['id']]);
          admin_sync_project_gallery($pdo, $projectId);
          admin_set_flash_and_redirect('success', 'Media item deleted.');
        }
      }
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $flashError = $e->getMessage();
  }
}

$pdo->exec(
  "CREATE TABLE IF NOT EXISTS website_testimonials (
     id INT AUTO_INCREMENT PRIMARY KEY,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     client_name VARCHAR(160) NOT NULL,
     project_type VARCHAR(80) NULL,
     rating TINYINT NOT NULL DEFAULT 5,
     message TEXT NOT NULL,
     project_ref VARCHAR(160) NULL,
     is_approved TINYINT(1) NOT NULL DEFAULT 0
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
  "CREATE TABLE IF NOT EXISTS website_project_updates (
     id INT AUTO_INCREMENT PRIMARY KEY,
     project_id VARCHAR(64) NOT NULL,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     title VARCHAR(160) NOT NULL,
     note TEXT NULL,
     photo_path VARCHAR(255) NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

ensure_client_portal_tables($pdo);

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$adminUsers = (int)$pdo->query("SELECT COUNT(*) FROM user_roles WHERE role = 'admin'")->fetchColumn();
$staffUsers = (int)$pdo->query("SELECT COUNT(*) FROM user_roles WHERE role <> 'admin'")->fetchColumn();
$journalEntries = (int)$pdo->query("SELECT COUNT(*) FROM purchase_entries")->fetchColumn();
$journalCash = (float)$pdo->query("SELECT COALESCE(SUM(cash),0) FROM purchase_entries")->fetchColumn();
$journalTotal = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM purchase_entries")->fetchColumn();
$clientCount = (int)$pdo->query("SELECT COUNT(*) FROM website_clients")->fetchColumn();
$projectAssignments = (int)$pdo->query("SELECT COUNT(*) FROM website_client_projects")->fetchColumn();
$testimonialsPending = (int)$pdo->query("SELECT COUNT(*) FROM website_testimonials WHERE is_approved = 0")->fetchColumn();
$projectUpdates = (int)$pdo->query("SELECT COUNT(*) FROM website_project_updates")->fetchColumn();
$maintenanceEnabled = maintenance_mode_is_enabled($pdo);
$maintenanceMessage = maintenance_mode_message($pdo);
$maintenanceRetryAfter = (int)site_setting_get($pdo, 'maintenance_retry_after', '3600');

$staffList = $pdo->query(
  "SELECT u.id, u.name, u.email, u.created_at,
          COALESCE(r.role, 'staff') AS role,
          p.job_title, p.department, p.phone, p.address, p.bio, p.updated_at AS profile_updated_at
   FROM users u
   LEFT JOIN user_roles r ON r.user_id = u.id
   LEFT JOIN staff_profiles p ON p.user_id = u.id
   ORDER BY u.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$recentActivity = $pdo->query(
  "SELECT l.created_at, l.action, l.details, u.name AS staff_name, a.name AS actor_name
   FROM staff_activity_log l
   INNER JOIN users u ON u.id = l.user_id
   LEFT JOIN users a ON a.id = l.actor_id
   ORDER BY l.created_at DESC
   LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC);

$recentJournal = $pdo->query(
  "SELECT date, supplier, total, cash
   FROM purchase_entries
   ORDER BY date DESC, id DESC
   LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$recentProjects = $pdo->query(
  "SELECT project_id, title, created_at
   FROM website_project_updates
   ORDER BY created_at DESC
   LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$contentProjects = $pdo->query(
  "SELECT id, slug, title, location, year, type, status, cover, before_image, after_image, summary, materials, gallery
   FROM website_projects
   ORDER BY id DESC
   LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC);

$contentServices = $pdo->query(
  "SELECT id, slug, name, desc_text, href, range_text, timeline_text
   FROM website_services
   ORDER BY id DESC
   LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC);

$projectMediaRows = $pdo->query(
  "SELECT pm.id, pm.project_id, pm.path, pm.created_at, p.title AS project_title
   FROM website_project_media pm
   INNER JOIN website_projects p ON p.id = pm.project_id
   WHERE pm.media_type = 'gallery'
   ORDER BY pm.created_at DESC, pm.id DESC
   LIMIT 24"
)->fetchAll(PDO::FETCH_ASSOC);

$permissionCatalog = permission_catalog();
$roles = $pdo->query("SELECT slug, name, is_system, created_at, updated_at FROM roles ORDER BY is_system DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$rolePermissions = [];
$rolePermRows = $pdo->query("SELECT role_slug, permission_key FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rolePermRows as $row) {
  $rolePermissions[(string)$row['role_slug']][] = (string)$row['permission_key'];
}

$title = 'Admin Dashboard';
include __DIR__ . '/templates/header.php';
?>
<style>
  .admin-shell {
    background:
      radial-gradient(circle at top left, rgba(13,110,253,.10), transparent 30%),
      radial-gradient(circle at top right, rgba(32,201,151,.10), transparent 25%),
      linear-gradient(180deg, #f8fafc 0%, #f3f6fb 100%);
    border-radius: 1.5rem;
  }
  .admin-hero {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(255,255,255,.78);
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 60px rgba(15,23,42,.08);
  }
  .admin-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 14px 40px rgba(15,23,42,.06);
    border-radius: 1rem;
    background: #fff;
  }
  .admin-tabs .nav-link {
    border-radius: 999px;
    font-weight: 600;
    color: #334155;
  }
  .admin-tabs .nav-link.active {
    background: #0d6efd;
    color: #fff;
    box-shadow: 0 10px 24px rgba(13,110,253,.22);
  }
  .stat-tile {
    min-height: 110px;
    border-radius: 1rem;
    background: linear-gradient(180deg, #fff, #f8fafc);
    border: 1px solid rgba(15,23,42,.06);
  }
  .table thead th {
    color: #64748b;
    text-transform: uppercase;
    font-size: .74rem;
    letter-spacing: .04em;
  }
</style>
<div class="container py-4">
  <div class="admin-shell p-3 p-lg-4">
    <div class="admin-hero p-4 p-lg-5 mb-4">
      <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
        <div class="me-3">
          <div class="text-uppercase small text-primary fw-semibold mb-2">Admin Workspace</div>
          <h1 class="display-6 fw-bold mb-2">Operations command center</h1>
          <div class="text-secondary">Manage staff, roles, profiles, activity, and maintenance from one polished workspace.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" data-mode="create">Add staff</button>
          <a class="btn btn-outline-secondary" href="purchase_list.php">Journal</a>
          <a class="btn btn-outline-danger" href="database_import.php">Import SQL</a>
          <a class="btn btn-outline-dark" href="database_export.php">Backup</a>
        </div>
      </div>

      <div class="row g-3 mt-3">
        <div class="col-md-3"><div class="stat-tile p-3"><div class="text-muted small">Total users</div><div class="fs-2 fw-bold"><?= number_format($totalUsers) ?></div></div></div>
        <div class="col-md-3"><div class="stat-tile p-3"><div class="text-muted small">Admin users</div><div class="fs-2 fw-bold"><?= number_format($adminUsers) ?></div></div></div>
        <div class="col-md-3"><div class="stat-tile p-3"><div class="text-muted small">Staff accounts</div><div class="fs-2 fw-bold"><?= number_format($staffUsers) ?></div></div></div>
        <div class="col-md-3"><div class="stat-tile p-3"><div class="text-muted small">Project updates</div><div class="fs-2 fw-bold"><?= number_format($projectUpdates) ?></div></div></div>
      </div>
    </div>

  <?php if ($maintenanceSaved): ?>
    <div class="alert alert-success">Maintenance settings updated.</div>
  <?php endif; ?>
  <?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <ul class="nav nav-pills gap-2 admin-tabs mb-3" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">Overview</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-roles" type="button" role="tab">Roles</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-staff" type="button" role="tab">Staff</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button" role="tab">Activity</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-maintenance" type="button" role="tab">Maintenance</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-8">
      <div class="card admin-card h-100">
        <div class="card-header bg-white fw-semibold">Quick actions</div>
        <div class="card-body d-flex flex-wrap gap-2">
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" data-mode="create">Add staff</button>
              <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#maintenanceModal">Edit maintenance</button>
              <a class="btn btn-outline-secondary" href="journal_export.php">Journal export</a>
              <a class="btn btn-outline-secondary" href="journal_import.php">Journal import</a>
              <a class="btn btn-outline-secondary" href="admin_about.php">Edit About page</a>
              <a class="btn btn-outline-secondary" href="testimonials_admin.php">Testimonials</a>
              <a class="btn btn-outline-secondary" href="project_updates_admin.php">Project updates</a>
              <a class="btn btn-outline-secondary" href="client_portal_admin.php">Client portal</a>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold">Status</div>
            <div class="card-body">
              <div class="d-flex justify-content-between border-bottom py-2"><span>Maintenance</span><strong><?= $maintenanceEnabled ? 'On' : 'Off' ?></strong></div>
              <div class="d-flex justify-content-between border-bottom py-2"><span>Clients</span><strong><?= number_format($clientCount) ?></strong></div>
              <div class="d-flex justify-content-between border-bottom py-2"><span>Assignments</span><strong><?= number_format($projectAssignments) ?></strong></div>
              <div class="d-flex justify-content-between py-2"><span>Pending testimonials</span><strong><?= number_format($testimonialsPending) ?></strong></div>
            </div>
          </div>
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-lg-6">
          <div class="card admin-card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Projects</span>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal" data-project="<?= admin_project_payload_json([]) ?>">Add project</button>
            </div>
            <div class="table-responsive">
              <table class="table mb-0 align-middle">
                <thead><tr><th>Project</th><th>Status</th><th></th></tr></thead>
                <tbody>
                  <?php if (!$contentProjects): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No projects yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($contentProjects as $p): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="text-muted small"><?= htmlspecialchars((string)($p['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><span class="badge text-bg-light border"><?= htmlspecialchars((string)($p['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="text-end text-nowrap">
                          <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#projectModal"
                            data-project="<?= admin_project_payload_json($p) ?>"
                            data-project-id="<?= (int)$p['id'] ?>"
                          >Edit</button>
                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this project?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete_project">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card admin-card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Services</span>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">Add service</button>
            </div>
            <div class="table-responsive">
              <table class="table mb-0 align-middle">
                <thead><tr><th>Service</th><th>Range</th><th></th></tr></thead>
                <tbody>
                  <?php if (!$contentServices): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No services yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($contentServices as $s): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="text-muted small"><?= htmlspecialchars((string)$s['desc_text'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars((string)($s['range_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end text-nowrap">
                          <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#serviceModal"
                            data-id="<?= (int)$s['id'] ?>"
                            data-slug="<?= htmlspecialchars((string)$s['slug'], ENT_QUOTES, 'UTF-8') ?>"
                            data-name="<?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-desc="<?= htmlspecialchars((string)$s['desc_text'], ENT_QUOTES, 'UTF-8') ?>"
                            data-href="<?= htmlspecialchars((string)($s['href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-range="<?= htmlspecialchars((string)($s['range_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-timeline="<?= htmlspecialchars((string)($s['timeline_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                          >Edit</button>
                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this service?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-roles" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold">Create role</div>
            <div class="card-body">
              <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="create_role">
                <div class="col-12">
                  <label class="form-label">Role slug</label>
                  <input type="text" name="slug" class="form-control" placeholder="supervisor" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Role name</label>
                  <input type="text" name="name" class="form-control" placeholder="Supervisor" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Permissions</label>
                  <div class="border rounded-3 p-3" style="max-height: 340px; overflow:auto;">
                    <div class="row g-2">
                      <?php foreach ($permissionCatalog as $key => $label): ?>
                        <div class="col-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" id="create_perm_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                            <label class="form-check-label" for="create_perm_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <button class="btn btn-primary">Create role</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card admin-card">
            <div class="card-header bg-white fw-semibold">Existing roles</div>
            <div class="card-body">
              <?php foreach ($roles as $roleRow): ?>
                <?php $roleSlug = (string)$roleRow['slug']; $selectedPermissions = $rolePermissions[$roleSlug] ?? []; ?>
                <div class="border rounded-3 p-3 mb-3 bg-light">
                  <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($roleSlug, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-md-4">
                      <label class="form-label">Slug</label>
                      <input type="text" class="form-control" value="<?= htmlspecialchars($roleSlug, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-8">
                      <label class="form-label">Name</label>
                      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars((string)$roleRow['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $roleRow['is_system'] ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Permissions</label>
                      <div class="row g-2">
                        <?php foreach ($permissionCatalog as $key => $label): ?>
                          <div class="col-md-6">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" id="perm_<?= htmlspecialchars($roleSlug . '_' . $key, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($key, $selectedPermissions, true) ? 'checked' : '' ?> <?= $roleSlug === 'admin' ? 'checked disabled' : '' ?>>
                              <label class="form-check-label" for="perm_<?= htmlspecialchars($roleSlug . '_' . $key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                      <?php if ($roleSlug === 'admin'): ?>
                        <div class="form-text">Admin always has all permissions.</div>
                      <?php endif; ?>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-primary" <?= ($roleSlug === 'admin') ? 'disabled' : '' ?>>Save role</button>
                    </div>
                  </form>
                  <?php if (!$roleRow['is_system']): ?>
                    <form method="post" class="mt-2" onsubmit="return confirm('Delete this role? Users with it will be moved to staff.')">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="delete_role">
                      <input type="hidden" name="slug" value="<?= htmlspecialchars($roleSlug, ENT_QUOTES, 'UTF-8') ?>">
                      <button class="btn btn-outline-danger btn-sm" type="submit">Delete role</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-staff" role="tabpanel">
      <div class="card admin-card">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <div class="fw-semibold">Staff directory</div>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" data-mode="create">Add staff</button>
        </div>
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead>
              <tr><th>Staff</th><th>Role</th><th>Profile</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($staffList as $staff): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars((string)$staff['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars((string)$staff['email'], ENT_QUOTES, 'UTF-8') ?></div>
                  </td>
                  <td><span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst((string)$staff['role']), ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td>
                    <div class="small">
                      <div><span class="text-muted">Title:</span> <?= htmlspecialchars((string)($staff['job_title'] ?? 'Not set'), ENT_QUOTES, 'UTF-8') ?></div>
                      <div><span class="text-muted">Dept:</span> <?= htmlspecialchars((string)($staff['department'] ?? 'Not set'), ENT_QUOTES, 'UTF-8') ?></div>
                      <div><span class="text-muted">Updated:</span> <?= htmlspecialchars((string)($staff['profile_updated_at'] ?? $staff['created_at']), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </td>
                  <td class="text-nowrap">
                    <button
                      class="btn btn-sm btn-outline-primary me-1"
                      data-bs-toggle="modal"
                      data-bs-target="#staffEditModal"
                      data-user-id="<?= (int)$staff['id'] ?>"
                      data-name="<?= htmlspecialchars((string)$staff['name'], ENT_QUOTES, 'UTF-8') ?>"
                      data-email="<?= htmlspecialchars((string)$staff['email'], ENT_QUOTES, 'UTF-8') ?>"
                      data-role="<?= htmlspecialchars((string)$staff['role'], ENT_QUOTES, 'UTF-8') ?>"
                      data-job-title="<?= htmlspecialchars((string)($staff['job_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-department="<?= htmlspecialchars((string)($staff['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-phone="<?= htmlspecialchars((string)($staff['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-address="<?= htmlspecialchars((string)($staff['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-bio="<?= htmlspecialchars((string)($staff['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >Edit</button>
                    <button
                      class="btn btn-sm btn-outline-danger"
                      data-bs-toggle="modal"
                      data-bs-target="#passwordModal"
                      data-user-id="<?= (int)$staff['id'] ?>"
                      data-name="<?= htmlspecialchars((string)$staff['name'], ENT_QUOTES, 'UTF-8') ?>"
                    >Reset pass</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-activity" role="tabpanel">
      <div class="card admin-card">
        <div class="card-header bg-white fw-semibold">Recent staff activity</div>
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead>
              <tr><th>Time</th><th>Staff</th><th>Action</th><th>Details</th></tr>
            </thead>
            <tbody>
              <?php if (!$recentActivity): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No staff activity yet.</td></tr>
              <?php else: ?>
                <?php foreach ($recentActivity as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['staff_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-light border"><?= htmlspecialchars((string)$row['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="text-secondary">
                      <?= htmlspecialchars((string)($row['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                      <?php if (!empty($row['actor_name'])): ?>
                        <div class="small">By <?= htmlspecialchars((string)$row['actor_name'], ENT_QUOTES, 'UTF-8') ?></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-maintenance" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold">Maintenance status</div>
            <div class="card-body">
              <div class="d-flex justify-content-between border-bottom py-2"><span>Status</span><strong><?= $maintenanceEnabled ? 'Enabled' : 'Disabled' ?></strong></div>
              <div class="d-flex justify-content-between border-bottom py-2"><span>Retry after</span><strong><?= (int)$maintenanceRetryAfter ?>s</strong></div>
              <div class="pt-3 text-secondary small"><?= htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card admin-card h-100">
            <div class="card-header fw-semibold">Quick edit</div>
            <div class="card-body">
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#maintenanceModal">Open maintenance editor</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form method="post" enctype="multipart/form-data">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-0">Add staff</h5>
              <div class="text-muted small">Create a new staff account and profile.</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_staff">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" autocomplete="new-password" required></div>
              <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="staff">Staff</option>
                  <option value="admin">Admin</option>
                  <option value="accounting">Accounting</option>
                  <option value="warehouse">Warehouse</option>
                </select>
              </div>
              <div class="col-md-6"><label class="form-label">Job title</label><input type="text" name="job_title" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Department</label><input type="text" name="department" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
              <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control" rows="3"></textarea></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create staff</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="staffEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form method="post">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-0">Edit staff</h5>
              <div class="text-muted small" id="staffEditSubtitle">Update account, role, and profile details.</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="update_staff">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
              <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" id="edit_role" class="form-select">
                  <option value="staff">Staff</option>
                  <option value="admin">Admin</option>
                  <option value="accounting">Accounting</option>
                  <option value="warehouse">Warehouse</option>
                </select>
              </div>
              <div class="col-md-6"><label class="form-label">Job title</label><input type="text" name="job_title" id="edit_job_title" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Department</label><input type="text" name="department" id="edit_department" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Address</label><input type="text" name="address" id="edit_address" class="form-control"></div>
              <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" id="edit_bio" class="form-control" rows="4"></textarea></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title">Reset password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="password_user_id" value="">
            <div class="mb-3">
              <label class="form-label">New password for <span id="password_staff_name" class="fw-semibold"></span></label>
              <input type="password" name="password" class="form-control" autocomplete="new-password" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Reset password</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="maintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form method="post">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-0">Maintenance settings</h5>
              <div class="text-muted small">Control site access and maintenance messaging.</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save_maintenance">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-check form-switch fs-5">
                  <input class="form-check-input" type="checkbox" role="switch" id="modal_maintenance_mode" name="maintenance_mode" value="1" <?= $maintenanceEnabled ? 'checked' : '' ?>>
                  <label class="form-check-label" for="modal_maintenance_mode">Enable maintenance mode</label>
                </div>
              </div>
              <div class="col-md-8">
                <label class="form-label" for="modal_maintenance_message">Maintenance message</label>
                <textarea class="form-control" id="modal_maintenance_message" name="maintenance_message" rows="4"><?= htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="modal_maintenance_retry_after">Retry after (seconds)</label>
                <input type="number" min="60" step="60" class="form-control" id="modal_maintenance_retry_after" name="maintenance_retry_after" value="<?= (int)$maintenanceRetryAfter ?>">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Save maintenance</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="projectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="post" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">Project</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save_project">
            <input type="hidden" name="id" id="project_id">
            <input type="hidden" name="original_slug" id="project_original_slug">
            <div class="row g-3">
              <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" id="project_slug" required></div>
              <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title" id="project_title" required></div>
              <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location" id="project_location"></div>
              <div class="col-md-2"><label class="form-label">Year</label><input class="form-control" name="year" id="project_year"></div>
              <div class="col-md-3"><label class="form-label">Type</label><input class="form-control" name="type" id="project_type"></div>
              <div class="col-md-3"><label class="form-label">Status</label><input class="form-control" name="status" id="project_status" value="Ongoing"></div>
              <div class="col-12">
                <label class="form-label">Cover upload</label>
                <input type="hidden" name="remove_cover" id="project_remove_cover" value="0">
                <input class="form-control" type="file" name="cover_upload" accept="image/*">
                <div class="form-text">Leave blank to keep the current cover.</div>
                <div class="mt-2 d-none" id="project_cover_preview_wrap">
                  <div class="border rounded-3 p-2 bg-light d-inline-flex flex-column gap-2" style="width: 180px;">
                    <img id="project_cover_preview" src="" alt="Current cover" class="img-fluid rounded" style="height:110px; object-fit:cover; width:100%;">
                    <div class="d-flex gap-2">
                      <a id="project_cover_link" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary flex-fill">View</a>
                      <button type="button" class="btn btn-sm btn-outline-danger flex-fill" data-remove-media="cover">Remove</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Before upload</label>
                <input type="hidden" name="remove_before_image" id="project_remove_before_image" value="0">
                <input class="form-control" type="file" name="before_upload" accept="image/*">
                <div class="mt-2 d-none" id="project_before_preview_wrap">
                  <div class="border rounded-3 p-2 bg-light d-inline-flex flex-column gap-2" style="width: 180px;">
                    <img id="project_before_preview" src="" alt="Current before image" class="img-fluid rounded" style="height:110px; object-fit:cover; width:100%;">
                    <div class="d-flex gap-2">
                      <a id="project_before_link" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary flex-fill">View</a>
                      <button type="button" class="btn btn-sm btn-outline-danger flex-fill" data-remove-media="before">Remove</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">After upload</label>
                <input type="hidden" name="remove_after_image" id="project_remove_after_image" value="0">
                <input class="form-control" type="file" name="after_upload" accept="image/*">
                <div class="mt-2 d-none" id="project_after_preview_wrap">
                  <div class="border rounded-3 p-2 bg-light d-inline-flex flex-column gap-2" style="width: 180px;">
                    <img id="project_after_preview" src="" alt="Current after image" class="img-fluid rounded" style="height:110px; object-fit:cover; width:100%;">
                    <div class="d-flex gap-2">
                      <a id="project_after_link" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary flex-fill">View</a>
                      <button type="button" class="btn btn-sm btn-outline-danger flex-fill" data-remove-media="after">Remove</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Gallery uploads</label>
                <input class="form-control" type="file" name="gallery_uploads[]" accept="image/*" multiple>
                <div class="form-text">Upload one or more images to add to the project gallery.</div>
              </div>
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                  <label class="form-label mb-0">Recent uploads</label>
                  <div class="small text-muted">Latest gallery files for this project</div>
                </div>
                <div id="project_recent_uploads" class="d-flex flex-wrap gap-2 mt-2"></div>
              </div>
              <div class="col-12"><label class="form-label">Materials</label><textarea class="form-control" rows="3" name="materials_text" id="project_materials_text" placeholder="One material per line or comma-separated"></textarea></div>
              <div class="col-12"><label class="form-label">Summary</label><textarea class="form-control" rows="4" name="summary" id="project_summary"></textarea></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save project</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title">Service</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save_service">
            <input type="hidden" name="id" id="service_id">
            <div class="row g-3">
              <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" id="service_slug" required></div>
              <div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" id="service_name" required></div>
              <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" rows="4" name="desc_text" id="service_desc" required></textarea></div>
              <div class="col-md-6"><label class="form-label">Href</label><input class="form-control" name="href" id="service_href" placeholder="/public/services/..."></div>
              <div class="col-md-3"><label class="form-label">Range</label><input class="form-control" name="range_text" id="service_range"></div>
              <div class="col-md-3"><label class="form-label">Timeline</label><input class="form-control" name="timeline_text" id="service_timeline"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save service</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php
    $mediaBuckets = [];
    foreach ($projectMediaRows as $mediaRow) {
      $pid = (int)$mediaRow['project_id'];
      if (!isset($mediaBuckets[$pid])) {
        $mediaBuckets[$pid] = [];
      }
      $mediaBuckets[$pid][] = $mediaRow;
    }
  ?>
  <div class="d-none" id="project_media_buckets">
    <?php foreach ($mediaBuckets as $pid => $items): ?>
      <div data-project-id="<?= (int)$pid ?>">
        <?php foreach ($items as $m): ?>
          <div class="border rounded-3 p-2 bg-light d-inline-flex flex-column gap-2 me-2 mb-2 align-top" style="width: 140px;">
            <?php $mediaUrl = admin_project_media_url((string)$m['path']); ?>
            <a href="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
              <img src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="img-fluid rounded" style="height:90px; object-fit:cover; width:100%;">
            </a>
            <div class="small text-muted text-truncate"><?= htmlspecialchars((string)($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <form method="post" onsubmit="return confirm('Delete this gallery image?')">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete_project_media">
              <input type="hidden" name="project_id" value="<?= (int)$pid ?>">
              <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-sm btn-outline-danger w-100">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header fw-semibold">Recent journal entries</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr><th>Date</th><th>Supplier</th><th class="text-end">Total</th><th class="text-end">Cash</th></tr>
              </thead>
              <tbody>
                <?php if (!$recentJournal): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No entries yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($recentJournal as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)$row['date'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)$row['supplier'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="text-end"><?= number_format((float)$row['total'], 2) ?></td>
                      <td class="text-end"><?= number_format((float)$row['cash'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header fw-semibold">Recent project updates</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr><th>Project</th><th>Title</th><th>Date</th></tr>
              </thead>
              <tbody>
                <?php if (!$recentProjects): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No project updates yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($recentProjects as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)$row['project_id'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const staffEditModal = document.getElementById('staffEditModal');
  const staffModal = document.getElementById('staffModal');
  const passwordModal = document.getElementById('passwordModal');
  const maintenanceModal = document.getElementById('maintenanceModal');
  const tabButtons = document.querySelectorAll('#adminTabs [data-bs-toggle="tab"]');
  const bootstrapTabs = window.bootstrap?.Tab || null;

  const setTabHash = (target) => {
    if (target) {
      history.replaceState(null, '', target);
    }
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener('shown.bs.tab', (event) => {
      setTabHash(event.target.getAttribute('data-bs-target'));
    });
  });

  if (location.hash) {
    const initialTab = document.querySelector(`#adminTabs [data-bs-target="${location.hash}"]`);
    if (initialTab && bootstrapTabs) {
      bootstrapTabs.getOrCreateInstance(initialTab).show();
    }
  }

  staffEditModal?.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) return;
    document.getElementById('edit_user_id').value = button.getAttribute('data-user-id') || '';
    document.getElementById('edit_name').value = button.getAttribute('data-name') || '';
    document.getElementById('edit_email').value = button.getAttribute('data-email') || '';
    document.getElementById('edit_role').value = button.getAttribute('data-role') || 'staff';
    document.getElementById('edit_job_title').value = button.getAttribute('data-job-title') || '';
    document.getElementById('edit_department').value = button.getAttribute('data-department') || '';
    document.getElementById('edit_phone').value = button.getAttribute('data-phone') || '';
    document.getElementById('edit_address').value = button.getAttribute('data-address') || '';
    document.getElementById('edit_bio').value = button.getAttribute('data-bio') || '';
    document.getElementById('staffEditSubtitle').textContent = `Editing ${button.getAttribute('data-name') || 'staff member'}`;
  });

  passwordModal?.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) return;
    document.getElementById('password_user_id').value = button.getAttribute('data-user-id') || '';
    document.getElementById('password_staff_name').textContent = button.getAttribute('data-name') || 'staff member';
  });

  staffModal?.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    const title = staffModal.querySelector('.modal-title');
    const subtitle = staffModal.querySelector('.text-muted.small');
    const action = staffModal.querySelector('input[name="action"]');
    const form = staffModal.querySelector('form');
    if (!button || !title || !subtitle || !action || !form) return;
    const mode = button.getAttribute('data-mode') || 'create';
    if (mode === 'edit') {
      title.textContent = 'Add staff';
      subtitle.textContent = 'Create a new staff account and profile.';
    }
  });

  maintenanceModal?.addEventListener('show.bs.modal', () => {
    const alertTab = document.querySelector('#adminTabs [data-bs-target="#tab-maintenance"]');
    if (alertTab && bootstrapTabs) bootstrapTabs.getOrCreateInstance(alertTab).show();
  });

  const fill = (modalId, map) => {
    const modal = document.getElementById(modalId);
    modal?.addEventListener('show.bs.modal', (event) => {
      const button = event.relatedTarget;
      if (!button) return;
      Object.entries(map).forEach(([targetId, attr]) => {
        const el = document.getElementById(targetId);
        if (el) el.value = button.getAttribute(attr) || '';
      });
    });
  };

  const recentUploadsHost = document.getElementById('project_recent_uploads');
  const mediaBuckets = document.getElementById('project_media_buckets');
  const defaultRecent = recentUploadsHost ? recentUploadsHost.innerHTML : '';
  const projectModal = document.getElementById('projectModal');

  const mediaPreviewMap = {
    cover: {
      wrap: document.getElementById('project_cover_preview_wrap'),
      image: document.getElementById('project_cover_preview'),
      link: document.getElementById('project_cover_link'),
      remove: document.getElementById('project_remove_cover'),
      attr: 'data-cover-url'
    },
    before: {
      wrap: document.getElementById('project_before_preview_wrap'),
      image: document.getElementById('project_before_preview'),
      link: document.getElementById('project_before_link'),
      remove: document.getElementById('project_remove_before_image'),
      attr: 'data-before-url'
    },
    after: {
      wrap: document.getElementById('project_after_preview_wrap'),
      image: document.getElementById('project_after_preview'),
      link: document.getElementById('project_after_link'),
      remove: document.getElementById('project_remove_after_image'),
      attr: 'data-after-url'
    }
  };

  const resetMediaPreview = (key) => {
    const config = mediaPreviewMap[key];
    if (!config) return;
    if (config.wrap) config.wrap.classList.add('d-none');
    if (config.image) config.image.src = '';
    if (config.link) config.link.href = '#';
    if (config.remove) config.remove.value = '0';
  };

  const setMediaPreview = (key, url) => {
    const config = mediaPreviewMap[key];
    if (!config) return;
    if (!url) {
      resetMediaPreview(key);
      return;
    }
    if (config.wrap) config.wrap.classList.remove('d-none');
    if (config.image) config.image.src = url;
    if (config.link) config.link.href = url;
    if (config.remove) config.remove.value = '0';
  };

  const projectFieldDefaults = {
    project_id: '',
    project_original_slug: '',
    project_slug: '',
    project_title: '',
    project_location: '',
    project_year: '',
    project_type: '',
    project_status: 'Ongoing',
    project_materials_text: '',
    project_summary: ''
  };

  const resetProjectForm = () => {
    Object.entries(projectFieldDefaults).forEach(([id, value]) => {
      const el = document.getElementById(id);
      if (el) el.value = value;
    });
    projectModal?.querySelectorAll('input[type="file"]').forEach((input) => {
      input.value = '';
    });
    Object.keys(mediaPreviewMap).forEach(resetMediaPreview);
  };

  const loadProjectModal = (button) => {
    resetProjectForm();
    const raw = button?.getAttribute('data-project') || '{}';
    let project = {};
    try {
      project = JSON.parse(raw);
    } catch (error) {
      project = {};
    }
    document.getElementById('project_id').value = project.id || '';
    document.getElementById('project_original_slug').value = project.originalSlug || project.slug || '';
    document.getElementById('project_slug').value = project.slug || '';
    document.getElementById('project_title').value = project.title || '';
    document.getElementById('project_location').value = project.location || '';
    document.getElementById('project_year').value = project.year || '';
    document.getElementById('project_type').value = project.type || '';
    document.getElementById('project_status').value = project.status || 'Ongoing';
    document.getElementById('project_materials_text').value = project.materialsText || '';
    document.getElementById('project_summary').value = project.summary || '';
    setMediaPreview('cover', project.coverUrl || '');
    setMediaPreview('before', project.beforeUrl || '');
    setMediaPreview('after', project.afterUrl || '');
    return String(project.id || button?.getAttribute('data-project-id') || '');
  };

  if (recentUploadsHost && mediaBuckets) {
    const refreshRecentUploads = (projectId) => {
      const bucket = mediaBuckets.querySelector(`[data-project-id="${projectId}"]`);
      recentUploadsHost.innerHTML = bucket ? bucket.innerHTML : '<div class="text-muted small">No gallery uploads yet.</div>';
    };

    projectModal?.addEventListener('show.bs.modal', (event) => {
      const button = event.relatedTarget;
      const projectId = button ? loadProjectModal(button) : '';
      refreshRecentUploads(projectId);
    });

    staffModal?.addEventListener('show.bs.modal', () => {
      recentUploadsHost.innerHTML = defaultRecent || '<div class="text-muted small">Open a project to view recent uploads.</div>';
    });
  }

  projectModal?.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) {
      resetProjectForm();
      return;
    }
    loadProjectModal(button);
  });

  projectModal?.addEventListener('hidden.bs.modal', () => {
    resetProjectForm();
  });

  document.querySelectorAll('[data-remove-media]').forEach((button) => {
    button.addEventListener('click', () => {
      const key = button.getAttribute('data-remove-media') || '';
      const config = mediaPreviewMap[key];
      if (!config) return;
      if (config.remove) config.remove.value = '1';
      if (config.wrap) config.wrap.classList.add('d-none');
    });
  });

  fill('serviceModal', {
    service_id: 'data-id',
    service_slug: 'data-slug',
    service_name: 'data-name',
    service_desc: 'data-desc',
    service_href: 'data-href',
    service_range: 'data-range',
    service_timeline: 'data-timeline'
  });
});
</script>
<?php include __DIR__ . '/templates/footer.php'; ?>
