<?php
require __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
try {
  $pdo = Db::pdo();
  $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
  echo json_encode(['ok'=>true,'version'=>$ver,'time'=>date('c')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
