<?php
ob_start();
session_start();
require_once __DIR__ . '/../src/config/db.php';

// --- ROTEAMENTO SIMPLES SEM .HTACCESS ---
$page = $_GET['page'] ?? 'auth/login';
$parts = explode('/', $page);
$controllerName = $parts[0] ?? 'auth';
$action = $parts[1] ?? 'login';

// Proteção básica
if (!isset($_SESSION['user_id']) && $controllerName !== 'auth') {
    header('Location: index.php?page=auth/login');
    exit;
}

// Carrega controller
switch ($controllerName) {
    case 'auth':
        require_once __DIR__ . '/../src/controllers/AuthController.php';
        $ctrl = new AuthController();
        if ($action === 'login') $ctrl->login();
        elseif ($action === 'register') $ctrl->register();
        elseif ($action === 'logout') $ctrl->logout();
        break;

    case 'admin':
        if ($_SESSION['tipo'] !== 'admin') die('Acesso negado');
        require_once __DIR__ . '/../src/controllers/AdminController.php';
        $ctrl = new AdminController();
        $ctrl->$action();
        break;

    case 'mentorado':
        if ($_SESSION['tipo'] !== 'mentorado' || !$_SESSION['aprovado']) die('Acesso negado');
        require_once __DIR__ . '/../src/controllers/MentoradoController.php';
        $ctrl = new MentoradoController();
        $ctrl->$action();
        break;

    default:
        echo "<h1>404 - Página não encontrada</h1>";
        echo "<p>Voltar ao <a href='index.php'>Login</a></p>";
}