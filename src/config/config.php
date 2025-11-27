<?php
/**
 * Configurações Gerais do Sistema
 * EGI - Escola de Gestão Imobiliária
 */

// Configurações da aplicação
define('APP_NAME', 'EGI - Escola de Gestão Imobiliária');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/egi');

// Caminhos
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// URLs
define('BASE_URL', APP_URL . '/public');
define('ASSETS_URL', BASE_URL . '/');
define('CSS_URL', BASE_URL . '/css');
define('JS_URL', BASE_URL . '/js');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Configurações de upload
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'mp4', 'avi']);

// Configurações de sessão
define('SESSION_LIFETIME', 7200); // 2 horas em segundos

// Configurações de gamificação
define('PONTOS_AULA_CONCLUIDA', 10);
define('PONTOS_ATIVIDADE_ENTREGUE', 50);
define('PONTOS_PRIMEIRA_ATIVIDADE', 50);
define('PENALIDADE_ATRASO_PERCENT', 20);

// Configurações de email (configurar SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu-email@gmail.com');
define('SMTP_PASS', 'sua-senha');
define('SMTP_FROM', 'noreply@egi.com.br');
define('SMTP_FROM_NAME', 'EGI Plataforma');

// Chave de segurança
define('SECURITY_KEY', 'egi_secure_key_2024_change_this');

// Incluir configurações de banco
require_once 'database.php';
?>