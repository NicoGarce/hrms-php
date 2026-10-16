<?php
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
header('Content-Type: application/json');
if (!is_logged_in()) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
$q = trim($_GET['q'] ?? '');
if ($q==='') { echo json_encode(['employees'=>[]]); exit; }
$like = "%$q%";
$params = [$like,$like,$like];
$sql = "SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name, e.employee_code as code FROM employees e WHERE e.deleted_at IS NULL AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
// Department Heads only see their own department here too.
$scopeDepts = managed_department_ids();
if ($scopeDepts !== null) {
    if (empty($scopeDepts)) { echo json_encode(['employees'=>[]]); exit; }
    $sql .= " AND e.department_id IN (" . implode(',', array_fill(0, count($scopeDepts), '?')) . ")";
    $params = array_merge($params, $scopeDepts);
}
$sql .= " LIMIT 5";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$emps = $stmt->fetchAll();
echo json_encode(['employees'=>$emps]);
