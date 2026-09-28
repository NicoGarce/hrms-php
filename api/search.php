<?php
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
header('Content-Type: application/json');
if (!is_logged_in()) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
$q = trim($_GET['q'] ?? '');
if ($q==='') { echo json_encode(['employees'=>[]]); exit; }
$like = "%$q%";
$stmt = db()->prepare("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name, e.employee_code as code FROM employees e WHERE e.deleted_at IS NULL AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?) LIMIT 5");
$stmt->execute([$like,$like,$like]);
$emps = $stmt->fetchAll();
echo json_encode(['employees'=>$emps]);
