<?php
// =============================================================
// LOGOUT.PHP — Encerrar Sessão
// =============================================================
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
require_once __DIR__ . '/src/Auth.php';
Auth::logout();
