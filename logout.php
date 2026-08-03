<?php
// =============================================================
// LOGOUT.PHP — Encerrar Sessão
// =============================================================
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
require_once __DIR__ . '/src/Auth.php';
// Limpa sessão e cookie; o método também redireciona para o login.
Auth::logout();
