#!/usr/bin/env php
<?php

// プロジェクトルート：/var/www/html/bin から ../ で /var/www/html
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/Services/GraveContractMgmtService.php';
require __DIR__ . '/../app/Services/GraveContractMgmtScheduler.php';

$result = GraveContractMgmtScheduler::run();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(0);
