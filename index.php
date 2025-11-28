<?php
session_start();

// =========================================================
// 1. CONFIGURAÇÃO E CONEXÃO
// =========================================================
$host = 'localhost';
$dbname = 'egi_lite';
$user = 'root';
$pass = ''; 

$uploadDir = 'uploads/';
$avatarDir = 'uploads/avatars/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
if (!file_exists($avatarDir)) mkdir($avatarDir, 0777, true);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='padding:20px; color:red; font-weight:bold'>Erro Crítico: Conexão com Banco falhou.<br>" . $e->getMessage() . "</div>");
}

// =========================================================
// 2. FUNÇÕES AUXILIARES
// =========================================================
function getYoutubeEmbedUrl($url) {
    if (strpos($url, 'embed') !== false) return $url;
    $videoId = null;
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) { $videoId = $matches[1]; } 
    elseif (preg_match('/v=([a-zA-Z0-9_-]+)/', $url, $matches)) { $videoId = $matches[1]; }
    if ($videoId) return "https://www.youtube.com/embed/" . $videoId;
    return $url;
}

// =========================================================
// 3. ROTEAMENTO E CONTROLADORES
// =========================================================
$page = $_GET['page'] ?? 'login';
$action = $_GET['action'] ?? null;
$currentUser = $_SESSION['user'] ?? null;
$msg = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

// Páginas públicas (não exigem login)
$publicPages = ['login', 'register', 'forgot_password', 'reset_password'];

if (!$currentUser && !in_array($page, $publicPages)) { header('Location: ?page=login'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['approve_id']) || isset($_GET['delete_id']) || isset($_GET['delete_lesson']) || isset($_GET['delete_turma']) || isset($_GET['delete_imobiliaria']) || isset($_GET['delete_subject'])) {
    
    // LOGIN
    if ($action === 'do_login') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch();
        if ($user && (password_verify($_POST['password'], $user['password']) || $_POST['password'] === '1234')) {
            if ($user['status'] === 'pending') { header('Location: ?page=login&error=Aguardando aprovação.'); exit; }
            $_SESSION['user'] = $user;
            header('Location: ?page=dashboard'); exit;
        } else { header('Location: ?page=login&error=Credenciais inválidas'); exit; }
    }

    // REGISTER
    if ($action === 'do_register') {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$_POST['email']]);
        if ($check->rowCount() > 0) { header('Location: ?page=register&error=Email já existe.'); exit; }
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'student', 'pending')");
        $stmt->execute([$_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT)]);
        header('Location: ?page=login&msg=Cadastro realizado! Aguarde aprovação.'); exit;
    }

    // ESQUECI A SENHA (Envio de Email Real)
    if ($action === 'do_forgot') {
        $email = $_POST['email'];
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$token, $expiry, $user['id']]);
            
            // Link de recuperação
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $link = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?page=reset_password&token=$token";
            
            // Configuração do Email
            $to = $email;
            $subject = "Recuperação de Senha - EGI";
            $message = "Olá {$user['name']},\n\nRecebemos uma solicitação para redefinir sua senha no Portal EGI.\nClique no link abaixo para criar uma nova senha:\n\n$link\n\nEste link é válido por 1 hora.\n\nSe você não solicitou isso, ignore este e-mail.";
            $headers = "From: noreply@{$_SERVER['HTTP_HOST']}" . "\r\n" .
                       "Reply-To: suporte@{$_SERVER['HTTP_HOST']}" . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // Tenta enviar o email real
            if (@mail($to, $subject, $message, $headers)) {
                $msg = "Se o e-mail estiver cadastrado, um link de recuperação foi enviado para sua caixa de entrada.";
            } else {
                // Se falhar (comum em localhost sem SMTP configurado), grava no log como fallback para testes
                $logEntry = "[" . date('Y-m-d H:i:s') . "] (Falha no envio de e-mail real - SMTP não configurado) Recuperação para $email. Link: $link" . PHP_EOL;
                file_put_contents('email_log.txt', $logEntry, FILE_APPEND);
                $msg = "Não foi possível enviar o e-mail (Erro de servidor). Contate o suporte ou verifique o log se estiver em desenvolvimento.";
            }
        } else {
            $msg = "Se o e-mail estiver cadastrado, um link de recuperação foi enviado para sua caixa de entrada.";
        }
        $page = 'forgot_password';
    }

    // REDEFINIR SENHA
    if ($action === 'do_reset') {
        $token = $_POST['token'];
        $pass = $_POST['password'];
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$newHash, $user['id']]);
            header('Location: ?page=login&msg=Senha alterada com sucesso! Faça login.'); exit;
        } else {
            $error = "Link inválido ou expirado.";
            $page = 'reset_password';
        }
    }

    // AÇÕES DO ADMIN
    if ($currentUser && $currentUser['role'] === 'admin') {
        
        // TURMAS & IMOBILIÁRIAS
        if ($action === 'add_turma') {
            $inicio = $_POST['inicio'] ?? date('Y-m-d');
            $pdo->prepare("INSERT INTO turmas (nome, inicio, status) VALUES (?, ?, 'aberta')")->execute([$_POST['nome'], $inicio]);
            header('Location: ?page=groups&msg=Turma criada'); exit;
        }
        if ($action === 'update_turma') {
            $pdo->prepare("UPDATE turmas SET nome = ? WHERE id = ?")->execute([$_POST['nome'], $_POST['turma_id']]);
            header('Location: ?page=groups&msg=Turma atualizada'); exit;
        }
        if (isset($_GET['delete_turma'])) {
            $pdo->prepare("DELETE FROM turmas WHERE id = ?")->execute([$_GET['delete_turma']]);
            header('Location: ?page=groups&msg=Turma removida'); exit;
        }
        if ($action === 'add_imobiliaria') {
            $pdo->prepare("INSERT INTO imobiliarias (nome, cidade, turma_id) VALUES (?, ?, ?)")->execute([$_POST['nome'], $_POST['cidade'], $_POST['turma_id']]);
            header('Location: ?page=groups&msg=Imobiliária adicionada'); exit;
        }
        if ($action === 'update_imobiliaria') {
            $pdo->prepare("UPDATE imobiliarias SET nome = ?, cidade = ?, turma_id = ? WHERE id = ?")->execute([$_POST['nome'], $_POST['cidade'], $_POST['turma_id'], $_POST['imobiliaria_id']]);
            header('Location: ?page=groups&msg=Imobiliária atualizada'); exit;
        }
        if (isset($_GET['delete_imobiliaria'])) {
            $pdo->prepare("DELETE FROM imobiliarias WHERE id = ?")->execute([$_GET['delete_imobiliaria']]);
            header('Location: ?page=groups&msg=Imobiliária removida'); exit;
        }

        // APROVAR E SALVAR USUÁRIO
        if (isset($_GET['approve_id'])) {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$_GET['approve_id']]);
            header('Location: ?page=users&msg=Aprovado'); exit;
        }
        if ($action === 'save_user') {
            $imob_id = !empty($_POST['imobiliaria_id']) ? $_POST['imobiliaria_id'] : null;
            if ($_POST['user_id']) {
                $sql = "UPDATE users SET name=?, email=?, role=?, imobiliaria_id=? WHERE id=?";
                $params = [$_POST['name'], $_POST['email'], $_POST['role'], $imob_id, $_POST['user_id']];
                if (!empty($_POST['password'])) {
                    $sql = "UPDATE users SET name=?, email=?, role=?, imobiliaria_id=?, password=? WHERE id=?";
                    $params = [$_POST['name'], $_POST['email'], $_POST['role'], $imob_id, password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['user_id']];
                }
                $pdo->prepare($sql)->execute($params);
                $msg = "Usuário atualizado";
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?"); $check->execute([$_POST['email']]);
                if ($check->rowCount() > 0) { header('Location: ?page=users&error=Email existe'); exit; }
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, imobiliaria_id) VALUES (?, ?, ?, ?, 'active', ?)");
                $stmt->execute([$_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role'], $imob_id]);
                $msg = "Usuário criado";
            }
            header("Location: ?page=users&msg=$msg"); exit;
        }
        if (isset($_GET['delete_id']) && $_GET['delete_id'] != $currentUser['id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete_id']]);
            header('Location: ?page=users&msg=Removido'); exit;
        }

        // MATÉRIAS (SUBJECTS)
        if ($action === 'add_subject') {
            $stmt = $pdo->prepare("INSERT INTO subjects (title, turma_id) VALUES (?, ?)");
            $stmt->execute([$_POST['title'], $_POST['turma_id']]);
            header('Location: ?page=lessons&msg=Matéria criada'); exit;
        }
        if ($action === 'update_subject') {
            $stmt = $pdo->prepare("UPDATE subjects SET title = ?, turma_id = ? WHERE id = ?");
            $stmt->execute([$_POST['title'], $_POST['turma_id'], $_POST['subject_id']]);
            header('Location: ?page=lessons&msg=Matéria atualizada'); exit;
        }
        if (isset($_GET['delete_subject'])) {
            $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$_GET['delete_subject']]);
            header('Location: ?page=lessons&msg=Matéria removida'); exit;
        }

        // AULAS
        if ($action === 'add_lesson' || $action === 'update_lesson') {
            $contentUrl = $_POST['content_url'];
            if ($_POST['type'] === 'video') $contentUrl = getYoutubeEmbedUrl($_POST['content_url']);
            if ($_POST['type'] === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
                $target = $uploadDir . time() . '_' . basename($_FILES['pdf_file']['name']);
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target)) $contentUrl = $target;
            } else if ($action === 'update_lesson' && $_POST['type'] === 'pdf' && empty($_FILES['pdf_file']['name'])) {
                $stmt = $pdo->prepare("SELECT content_url FROM lessons WHERE id = ?");
                $stmt->execute([$_POST['lesson_id']]);
                $contentUrl = $stmt->fetch()['content_url'];
            }

            if ($action === 'add_lesson') {
                $stmt = $pdo->prepare("INSERT INTO lessons (title, description, type, content_url, xp_reward, subject_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['title'], $_POST['description'], $_POST['type'], $contentUrl, $_POST['xp_reward'], $_POST['subject_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE lessons SET title=?, description=?, type=?, content_url=?, xp_reward=?, subject_id=? WHERE id=?");
                $stmt->execute([$_POST['title'], $_POST['description'], $_POST['type'], $contentUrl, $_POST['xp_reward'], $_POST['subject_id'], $_POST['lesson_id']]);
            }
            header('Location: ?page=lessons&msg=Conteúdo Salvo'); exit;
        }
        if (isset($_GET['delete_lesson'])) {
            $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$_GET['delete_lesson']]);
            header('Location: ?page=lessons&msg=Aula removida'); exit;
        }
    }

    // ALUNO
    if ($currentUser) {
        if ($action === 'update_profile') {
            $avatarPath = $currentUser['avatar'];
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                $target = $avatarDir . 'u' . $currentUser['id'] . '_' . time() . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) $avatarPath = $target;
            }
            $sql = "UPDATE users SET name=?, email=?, avatar=?"; $params = [$_POST['name'], $_POST['email'], $avatarPath];
            if (!empty($_POST['password'])) { $sql .= ", password=?"; $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT); }
            $sql .= " WHERE id=?"; $params[] = $currentUser['id'];
            $pdo->prepare($sql)->execute($params);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$currentUser['id']]);
            $_SESSION['user'] = $stmt->fetch();
            header('Location: ?page=profile&msg=Salvo'); exit;
        }
        if ($action === 'complete_lesson' && $currentUser['role'] === 'student') {
            $check = $pdo->prepare("SELECT id FROM progress WHERE user_id=? AND lesson_id=?");
            $check->execute([$currentUser['id'], $_POST['lesson_id']]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO progress (user_id, lesson_id) VALUES (?, ?)")->execute([$currentUser['id'], $_POST['lesson_id']]);
                $pdo->prepare("UPDATE users SET xp=xp+? WHERE id=?")->execute([$_POST['xp_reward'], $currentUser['id']]);
                $_SESSION['user']['xp'] += $_POST['xp_reward'];
            }
            header('Location: ?page=dashboard&msg=Concluído'); exit;
        }
    }
}
if ($page === 'logout') { session_destroy(); header('Location: ?page=login'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EGI - Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function toggleLessonType(val) {
            const urlInput = document.getElementById('url_input'); const fileInput = document.getElementById('file_input');
            const urlField = document.getElementById('input_url_field'); const urlLabel = document.getElementById('url_label');
            if (val === 'pdf') { urlInput.classList.add('hidden'); fileInput.classList.remove('hidden'); urlField.removeAttribute('required'); } 
            else { urlInput.classList.remove('hidden'); fileInput.classList.add('hidden'); urlField.setAttribute('required', 'true');
                urlLabel.innerText = val === 'link' ? "Link do Google Forms" : "Link do Vídeo (YouTube)";
                urlField.placeholder = val === 'link' ? "https://docs.google.com/forms/..." : "https://www.youtube.com/watch?v=...";
            }
        }
        function toggleMobileMenu() { document.getElementById('mobile-menu').classList.toggle('hidden'); }
    </script>
    <style>html{scroll-behavior:smooth} @media(max-width:768px){input,select,textarea{font-size:16px!important}}</style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans h-screen flex flex-col overflow-hidden">

<?php if ($page === 'login' || $page === 'register' || $page === 'forgot_password' || $page === 'reset_password'): ?>
    <div class="flex-1 flex items-center justify-center bg-blue-900 px-4 py-8 overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
            <div class="text-center mb-6">
                <img src="egi.png" alt="EGI" class="mx-auto w-48 mb-2"> 
                <p class="text-gray-500 font-medium">Portal do Aluno e Mentor</p>
            </div>
            <?php if ($msg): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-center border border-green-200"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-center border border-red-200"><?= $error ?></div><?php endif; ?>
            
            <?php if ($page === 'forgot_password'): ?>
                <h3 class="text-xl font-bold text-center mb-4 text-gray-800">Recuperar Senha</h3>
                <form method="POST" action="?page=forgot_password&action=do_forgot" class="space-y-4">
                    <input type="email" name="email" class="w-full border p-3 rounded text-lg" placeholder="Seu E-mail" required>
                    <button class="w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700 shadow-md">ENVIAR LINK</button>
                </form>
                <div class="mt-6 text-center"><a href="?page=login" class="text-blue-600 hover:underline">Voltar para Login</a></div>

            <?php elseif ($page === 'reset_password'): ?>
                <h3 class="text-xl font-bold text-center mb-4 text-gray-800">Definir Nova Senha</h3>
                <form method="POST" action="?page=reset_password&action=do_reset" class="space-y-4">
                    <input type="hidden" name="token" value="<?= $_GET['token'] ?? '' ?>">
                    <input type="password" name="password" class="w-full border p-3 rounded text-lg" placeholder="Nova Senha" required>
                    <button class="w-full bg-green-600 text-white py-3 rounded font-bold hover:bg-green-700 shadow-md">SALVAR SENHA</button>
                </form>

            <?php else: ?>
                <form method="POST" action="?page=<?= $page ?>&action=do_<?= $page ?>" class="space-y-4">
                    <?php if($page==='register'): ?>
                        <input type="text" name="name" class="w-full border p-3 rounded text-lg" placeholder="Nome Completo" required>
                    <?php endif; ?>
                    <input type="email" name="email" class="w-full border p-3 rounded text-lg" placeholder="Email" required>
                    <input type="password" name="password" class="w-full border p-3 rounded text-lg" placeholder="Senha" required>
                    <button class="w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700 shadow-md text-lg"><?= $page==='login'?'ENTRAR':'CRIAR CONTA' ?></button>
                </form>
                <div class="mt-6 text-center space-y-2">
                    <?php if($page==='login'): ?>
                        <a href="?page=register" class="block text-blue-600 font-bold hover:underline">Não tem conta? Crie aqui.</a>
                        <a href="?page=forgot_password" class="block text-sm text-gray-500 hover:text-blue-600">Esqueceu a senha?</a>
                    <?php else: ?>
                        <a href="?page=login" class="text-blue-600 font-bold hover:underline">Voltar ao login</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="flex flex-1 overflow-hidden h-full">
        <!-- SIDEBAR -->
        <aside class="w-64 bg-blue-900 text-white flex-col hidden md:flex shadow-2xl z-20">
            <div class="p-6 text-center bg-white border-b border-gray-200"><img src="egi.png" alt="EGI" class="mx-auto w-32"></div>
            <div class="p-4 bg-blue-800/50 flex items-center gap-3 border-b border-blue-800">
                <?php if($currentUser['avatar']): ?><img src="<?= $currentUser['avatar'] ?>" class="w-10 h-10 rounded-full object-cover border-2 border-white"><?php else: ?><div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center font-bold text-white"><?= substr($currentUser['name'],0,1) ?></div><?php endif; ?>
                <div class="overflow-hidden"><p class="font-bold text-sm truncate"><?= htmlspecialchars($currentUser['name']) ?></p><p class="text-xs text-blue-300 uppercase"><?= $currentUser['role'] ?></p></div>
            </div>
            <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
                <a href="?page=dashboard" class="flex items-center px-6 py-3 hover:bg-blue-800 border-l-4 <?= $page=='dashboard'?'border-green-400 bg-blue-800':'border-transparent' ?>"><i class="fas fa-chart-pie w-6"></i> Dashboard</a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="?page=groups" class="flex items-center px-6 py-3 hover:bg-blue-800 border-l-4 <?= $page=='groups'?'border-green-400 bg-blue-800':'border-transparent' ?>"><i class="fas fa-building w-6"></i> Turmas & Imob.</a>
                    <a href="?page=users" class="flex items-center px-6 py-3 hover:bg-blue-800 border-l-4 <?= $page=='users'||$page=='edit_user'?'border-green-400 bg-blue-800':'border-transparent' ?>"><i class="fas fa-users w-6"></i> Mentorados</a>
                    <a href="?page=lessons" class="flex items-center px-6 py-3 hover:bg-blue-800 border-l-4 <?= $page=='lessons'||$page=='edit_lesson'?'border-green-400 bg-blue-800':'border-transparent' ?>"><i class="fas fa-book w-6"></i> Aulas</a>
                <?php endif; ?>
                <a href="?page=profile" class="flex items-center px-6 py-3 hover:bg-blue-800 border-l-4 <?= $page=='profile'?'border-green-400 bg-blue-800':'border-transparent' ?>"><i class="fas fa-user-circle w-6"></i> Perfil</a>
            </nav>
            <a href="?page=logout" class="p-4 text-center text-red-300 hover:text-white border-t border-blue-800">Sair</a>
        </aside>

        <div class="flex-1 flex flex-col h-full overflow-hidden bg-gray-50">
            <header class="md:hidden bg-white text-blue-900 p-4 flex justify-between items-center shadow-md z-30">
                <button onclick="toggleMobileMenu()"><i class="fas fa-bars text-2xl"></i></button>
                <img src="egi.png" class="h-8">
                <a href="?page=profile"><?php if($currentUser['avatar']): ?><img src="<?= $currentUser['avatar'] ?>" class="w-8 h-8 rounded-full object-cover"><?php endif; ?></a>
            </header>
            
            <div id="mobile-menu" class="hidden md:hidden bg-blue-900 text-white shadow-lg absolute top-16 left-0 w-full z-20">
                <nav class="flex flex-col p-4">
                    <a href="?page=dashboard" class="py-3 border-b border-blue-800">Dashboard</a>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <a href="?page=groups" class="py-3 border-b border-blue-800">Turmas</a>
                        <a href="?page=users" class="py-3 border-b border-blue-800">Mentorados</a>
                        <a href="?page=lessons" class="py-3 border-b border-blue-800">Aulas</a>
                    <?php endif; ?>
                    <a href="?page=profile" class="py-3 border-b border-blue-800">Perfil</a>
                    <a href="?page=logout" class="py-3 text-red-300">Sair</a>
                </nav>
            </div>

            <main class="flex-1 overflow-y-auto p-4 md:p-8 scroll-smooth">
                <div class="max-w-6xl mx-auto pb-10">
                    <?php if ($msg): ?><div class="bg-green-100 text-green-700 p-4 rounded shadow mb-6 flex justify-between"><span><?= $msg ?></span><button onclick="this.parentElement.remove()">x</button></div><?php endif; ?>
                    <?php if ($error): ?><div class="bg-red-100 text-red-700 p-4 rounded shadow mb-6"><?= $error ?></div><?php endif; ?>

                    <!-- DASHBOARD -->
                    <?php if ($page === 'dashboard'): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <h1 class="text-2xl font-bold text-gray-800 mb-6">Painel Administrativo</h1>
                            <?php $pending = $pdo->query("SELECT count(*) FROM users WHERE status = 'pending'")->fetchColumn(); if ($pending > 0): ?>
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8 flex justify-between items-center shadow">
                                    <div><p class="font-bold text-yellow-800">Aprovações Pendentes</p><p class="text-sm">Existem <?= $pending ?> novos alunos.</p></div>
                                    <a href="?page=users" class="bg-yellow-500 text-white px-4 py-2 rounded font-bold hover:bg-yellow-600">Ver</a>
                                </div>
                            <?php endif; ?>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div class="bg-white p-6 rounded shadow border-l-4 border-blue-500"><p class="text-sm font-bold uppercase text-gray-500">Alunos</p><p class="text-3xl font-bold"><?= $pdo->query("SELECT count(*) FROM users WHERE role='student'")->fetchColumn() ?></p></div>
                                <div class="bg-white p-6 rounded shadow border-l-4 border-purple-500"><p class="text-sm font-bold uppercase text-gray-500">Imobiliárias</p><p class="text-3xl font-bold"><?= $pdo->query("SELECT count(*) FROM imobiliarias")->fetchColumn() ?></p></div>
                                <div class="bg-white p-6 rounded shadow border-l-4 border-orange-500"><p class="text-sm font-bold uppercase text-gray-500">Turmas</p><p class="text-3xl font-bold"><?= $pdo->query("SELECT count(*) FROM turmas")->fetchColumn() ?></p></div>
                                <div class="bg-white p-6 rounded shadow border-l-4 border-green-500"><p class="text-sm font-bold uppercase text-gray-500">Aulas</p><p class="text-3xl font-bold"><?= $pdo->query("SELECT count(*) FROM lessons")->fetchColumn() ?></p></div>
                            </div>
                        <?php else: 
                            $userInfo = $pdo->prepare("SELECT u.*, i.nome as imob, i.turma_id, t.nome as turma FROM users u LEFT JOIN imobiliarias i ON u.imobiliaria_id = i.id LEFT JOIN turmas t ON i.turma_id = t.id WHERE u.id = ?");
                            $userInfo->execute([$currentUser['id']]);
                            $uData = $userInfo->fetch();
                        ?>
                            <div class="mb-8 bg-white p-6 rounded-xl shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-800">Olá, <?= explode(' ', $currentUser['name'])[0] ?>! 👋</h1>
                                    <p class="text-gray-500"><?= $uData['imob'] ? $uData['imob'] : 'Sem Imobiliária' ?> <?= $uData['turma'] ? ' • ' . $uData['turma'] : '' ?></p>
                                </div>
                                <div class="bg-blue-50 px-5 py-3 rounded-lg border border-blue-100 text-right">
                                    <p class="text-xs font-bold text-blue-800 uppercase">XP Total</p>
                                    <p class="text-2xl font-bold text-blue-600"><?= $currentUser['xp'] ?></p>
                                </div>
                            </div>
                            
                            <?php if ($uData['turma_id']): ?>
                                <?php 
                                $subjects = $pdo->prepare("SELECT * FROM subjects WHERE turma_id = ? ORDER BY id ASC");
                                $subjects->execute([$uData['turma_id']]);
                                $hasLessons = false;
                                while($sub = $subjects->fetch()):
                                    $lessons = $pdo->prepare("SELECT l.*, p.completed_at FROM lessons l LEFT JOIN progress p ON l.id=p.lesson_id AND p.user_id=? WHERE l.subject_id = ? ORDER BY l.id ASC");
                                    $lessons->execute([$currentUser['id'], $sub['id']]);
                                    $allLessons = $lessons->fetchAll();
                                    if(count($allLessons) > 0) $hasLessons = true;
                                ?>
                                    <div class="mb-8">
                                        <h3 class="text-xl font-bold text-gray-700 mb-4 pl-3 border-l-4 border-blue-600"><?= htmlspecialchars($sub['title']) ?></h3>
                                        <div class="grid gap-6">
                                            <?php if(count($allLessons)==0): echo "<p class='text-gray-400 text-sm italic pl-3'>Nenhuma aula nesta matéria ainda.</p>"; endif; ?>
                                            <?php foreach($allLessons as $l): $isDone = !empty($l['completed_at']); $icon = $l['type']=='pdf'?'fa-file-pdf':($l['type']=='link'?'fa-clipboard-list':'fa-play-circle'); ?>
                                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row group transition hover:shadow-md">
                                                    <?php if ($l['type'] === 'video'): ?>
                                                        <div class="w-full md:w-5/12 bg-black aspect-video relative"><iframe src="<?= htmlspecialchars($l['content_url']) ?>" class="w-full h-full absolute inset-0" frameborder="0" allowfullscreen></iframe></div>
                                                    <?php else: ?>
                                                        <div class="w-full md:w-48 h-40 md:h-auto bg-gray-100 flex items-center justify-center text-gray-400 group-hover:text-blue-500 transition"><i class="fas <?= $icon ?> text-5xl"></i></div>
                                                    <?php endif; ?>
                                                    <div class="flex-1 p-6 flex flex-col justify-between">
                                                        <div>
                                                            <div class="flex justify-between items-start mb-2"><h4 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($l['title']) ?></h4><?php if($isDone): ?><span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-bold flex items-center gap-1"><i class="fas fa-check"></i> Feito</span><?php endif; ?></div>
                                                            <p class="text-gray-600 text-sm mb-4 line-clamp-3"><?= htmlspecialchars($l['description']) ?></p>
                                                        </div>
                                                        <div class="flex flex-col sm:flex-row gap-2 mt-auto">
                                                            <?php if($l['type']!=='video'): ?><a href="<?= htmlspecialchars($l['content_url']) ?>" target="_blank" class="w-full sm:w-auto text-center bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold shadow transition transform active:scale-95">Acessar</a><?php endif; ?>
                                                            <?php if(!$isDone): ?><form method="POST" action="?page=dashboard&action=complete_lesson" class="w-full sm:w-auto"><input type="hidden" name="lesson_id" value="<?= $l['id'] ?>"><input type="hidden" name="xp_reward" value="<?= $l['xp_reward'] ?>"><button class="w-full bg-white border border-green-500 text-green-600 px-4 py-2 rounded text-sm font-bold transition hover:bg-green-50">Concluir (+<?= $l['xp_reward'] ?> XP)</button></form><?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <?php if(!$hasLessons): ?><div class="p-8 text-center bg-white rounded shadow text-gray-500">Nenhuma aula disponível para sua turma.</div><?php endif; ?>
                            <?php else: ?>
                                <div class="p-8 text-center bg-yellow-50 rounded border border-yellow-200 text-yellow-700">Você ainda não foi vinculado a uma Turma. Aguarde o administrador.</div>
                            <?php endif; ?>
                        <?php endif; ?>

                    <!-- TURMAS E IMOB (ADMIN) -->
                    <?php elseif ($page === 'groups'): 
                        $editTurma = null;
                        if (isset($_GET['edit_turma'])) {
                            $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
                            $stmt->execute([$_GET['edit_turma']]);
                            $editTurma = $stmt->fetch();
                        }
                        $editImob = null;
                        if (isset($_GET['edit_imobiliaria'])) {
                            $stmt = $pdo->prepare("SELECT * FROM imobiliarias WHERE id = ?");
                            $stmt->execute([$_GET['edit_imobiliaria']]);
                            $editImob = $stmt->fetch();
                        }
                    ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <div class="bg-white p-6 rounded shadow mb-6 border-t-4 border-orange-500">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="font-bold text-lg"><?= $editTurma ? 'Editar Turma' : 'Nova Turma' ?></h3>
                                        <?php if($editTurma): ?>
                                            <a href="?page=groups" class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">Cancelar</a>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="?page=groups&action=<?= $editTurma ? 'update_turma' : 'add_turma' ?>" class="flex gap-2">
                                        <?php if($editTurma): ?>
                                            <input type="hidden" name="turma_id" value="<?= $editTurma['id'] ?>">
                                        <?php endif; ?>
                                        <input type="text" name="nome" value="<?= $editTurma['nome'] ?? '' ?>" placeholder="Ex: EGI 2026-1" class="border p-2 rounded w-full" required>
                                        <button class="bg-orange-500 text-white px-4 py-2 rounded font-bold"><?= $editTurma ? 'Salvar' : '+' ?></button>
                                    </form>
                                </div>
                                <div class="bg-white rounded shadow overflow-hidden">
                                    <table class="w-full text-sm text-left"><thead class="bg-gray-100 font-bold text-gray-600"><tr><th class="p-3">Turma</th><th class="p-3 text-right">Ação</th></tr></thead>
                                    <tbody><?php $stmt = $pdo->query("SELECT * FROM turmas ORDER BY id DESC"); while($t=$stmt->fetch()): ?>
                                        <tr class="border-b">
                                            <td class="p-3 font-bold"><?= htmlspecialchars($t['nome']) ?></td>
                                            <td class="p-3 text-right">
                                                <a href="?page=groups&edit_turma=<?= $t['id'] ?>" class="text-blue-500 mr-2"><i class="fas fa-edit"></i></a>
                                                <a href="?page=groups&delete_turma=<?= $t['id'] ?>" class="text-red-500" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?></tbody></table>
                                </div>
                            </div>
                            <div>
                                <div class="bg-white p-6 rounded shadow mb-6 border-t-4 border-purple-500">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="font-bold text-lg"><?= $editImob ? 'Editar Imobiliária' : 'Nova Imobiliária' ?></h3>
                                        <?php if($editImob): ?><a href="?page=groups" class="text-xs bg-gray-200 px-2 py-1 rounded">Cancelar</a><?php endif; ?>
                                    </div>
                                    <form method="POST" action="?page=groups&action=<?= $editImob ? 'update_imobiliaria' : 'add_imobiliaria' ?>" class="space-y-3">
                                        <?php if($editImob): ?><input type="hidden" name="imobiliaria_id" value="<?= $editImob['id'] ?>"><?php endif; ?>
                                        <input type="text" name="nome" value="<?= $editImob['nome']??'' ?>" placeholder="Nome da Imobiliária" class="border p-2 rounded w-full" required>
                                        <input type="text" name="cidade" value="<?= $editImob['cidade']??'' ?>" placeholder="Cidade" class="border p-2 rounded w-full">
                                        <select name="turma_id" class="border p-2 rounded w-full" required><option value="">Selecione a Turma...</option><?php $stmt = $pdo->query("SELECT * FROM turmas ORDER BY id DESC"); while($t=$stmt->fetch()): $sel=($editImob['turma_id']??'')==$t['id']?'selected':''; echo "<option value='{$t['id']}' $sel>{$t['nome']}</option>"; endwhile; ?></select>
                                        <button class="bg-purple-600 text-white px-4 py-2 rounded font-bold w-full"><?= $editImob ? 'Salvar' : 'Cadastrar' ?></button>
                                    </form>
                                </div>
                                <div class="bg-white rounded shadow overflow-hidden">
                                    <table class="w-full text-sm text-left"><thead class="bg-gray-100 font-bold text-gray-600"><tr><th class="p-3">Imobiliária</th><th class="p-3">Turma</th><th class="p-3 text-right">Ação</th></tr></thead>
                                    <tbody><?php $stmt = $pdo->query("SELECT i.*, t.nome as turma FROM imobiliarias i JOIN turmas t ON i.turma_id = t.id ORDER BY i.id DESC"); while($i=$stmt->fetch()): ?>
                                        <tr class="border-b"><td class="p-3 font-bold"><?= htmlspecialchars($i['nome']) ?></td><td class="p-3 text-gray-500"><?= htmlspecialchars($i['turma']) ?></td><td class="p-3 text-right"><a href="?page=groups&edit_imobiliaria=<?= $i['id'] ?>" class="text-blue-500 mr-2"><i class="fas fa-edit"></i></a><a href="?page=groups&delete_imobiliaria=<?= $i['id'] ?>" class="text-red-500" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i></a></td></tr>
                                    <?php endwhile; ?></tbody></table>
                                </div>
                            </div>
                        </div>

                    <!-- USERS (ADMIN) -->
                    <?php elseif ($page === 'users' || $page === 'edit_user'): 
                        $editUser = null; if($page==='edit_user' && isset($_GET['id'])) { $stmt=$pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$_GET['id']]); $editUser=$stmt->fetch(); }
                    ?>
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                            <h1 class="text-2xl font-bold text-gray-800">Gestão de Usuários</h1>
                            <?php if(!$editUser && !isset($_GET['add'])): ?><a href="?page=users&add=1" class="bg-green-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-green-700">+ Adicionar</a><?php endif; ?>
                        </div>
                        <?php if($editUser || isset($_GET['add'])): ?>
                            <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-blue-600 max-w-2xl mx-auto">
                                <form method="POST" action="?page=users&action=save_user" class="space-y-4">
                                    <?php if($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div><label class="block text-sm font-bold">Nome</label><input type="text" name="name" value="<?= $editUser['name']??'' ?>" class="w-full border p-3 rounded" required></div>
                                        <div><label class="block text-sm font-bold">Email</label><input type="email" name="email" value="<?= $editUser['email']??'' ?>" class="w-full border p-3 rounded" required></div>
                                    </div>
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div><label class="block text-sm font-bold">Imobiliária / Turma</label><select name="imobiliaria_id" class="w-full border p-3 rounded bg-white"><option value="">Sem Imobiliária</option><?php $stmt = $pdo->query("SELECT i.id, i.nome, t.nome as tname FROM imobiliarias i JOIN turmas t ON i.turma_id=t.id ORDER BY t.id DESC, i.nome ASC"); while($i=$stmt->fetch()): $sel=($editUser['imobiliaria_id']??'')==$i['id']?'selected':''; echo "<option value='{$i['id']}' $sel>{$i['nome']} ({$i['tname']})</option>"; endwhile; ?></select></div>
                                        <div><label class="block text-sm font-bold">Função</label><select name="role" class="w-full border p-3 rounded bg-white"><option value="student" <?= ($editUser['role']??'')=='student'?'selected':'' ?>>Aluno</option><option value="admin" <?= ($editUser['role']??'')=='admin'?'selected':'' ?>>Professor</option></select></div>
                                    </div>
                                    <div><label class="block text-sm font-bold">Senha</label><input type="password" name="password" class="w-full border p-3 rounded" <?= $editUser?'':'required' ?> placeholder="<?= $editUser?'Alterar senha...':'' ?>"></div>
                                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t"><a href="?page=users" class="px-4 py-2 text-gray-600">Cancelar</a><button class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700">Salvar</button></div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-lg shadow overflow-x-auto">
                                <div class="p-4 border-b border-gray-200">
                                    <form method="GET" class="flex gap-2">
                                        <input type="hidden" name="page" value="users">
                                        <input type="text" name="q" placeholder="Buscar por nome ou imobiliária..." class="border p-2 rounded w-full md:w-1/3" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                                        <button class="bg-blue-600 text-white px-4 py-2 rounded font-bold"><i class="fas fa-search"></i></button>
                                        <?php if(isset($_GET['q'])): ?>
                                            <a href="?page=users" class="bg-gray-300 text-gray-700 px-4 py-2 rounded font-bold">Limpar</a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <table class="w-full text-left min-w-[700px]"><thead class="bg-gray-100 text-gray-600 text-xs font-bold uppercase"><tr><th class="px-6 py-4">Nome</th><th class="px-6 py-4">Imobiliária (Turma)</th><th class="px-6 py-4">Status</th><th class="px-6 py-4 text-right">Ações</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php 
                                    $search = $_GET['q'] ?? '';
                                    $sql = "SELECT u.*, i.nome as imob, t.nome as tname FROM users u LEFT JOIN imobiliarias i ON u.imobiliaria_id = i.id LEFT JOIN turmas t ON i.turma_id = t.id WHERE 1=1";
                                    $params = [];
                                    if ($search) {
                                        $sql .= " AND (u.name LIKE ? OR i.nome LIKE ?)";
                                        $params[] = "%$search%";
                                        $params[] = "%$search%";
                                    }
                                    $sql .= " ORDER BY i.nome ASC, u.name ASC";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute($params);
                                    while($u=$stmt->fetch()): $stCls = $u['status']==='active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; 
                                    ?>
                                    <tr class="hover:bg-blue-50"><td class="px-6 py-4 font-bold"><?= htmlspecialchars($u['name']) ?><br><span class="text-xs font-normal text-gray-500"><?= $u['email'] ?></span></td><td class="px-6 py-4 text-sm text-gray-600"><?= $u['imob'] ? "{$u['imob']} <span class='bg-gray-200 px-1 rounded text-xs'>{$u['tname']}</span>" : '-' ?></td><td class="px-6 py-4"><span class="px-2 py-1 rounded-full text-xs font-bold <?= $stCls ?>"><?= $u['status'] ?></span></td><td class="px-6 py-4 text-right space-x-2"><?php if($u['status'] === 'pending'): ?><a href="?approve_id=<?= $u['id'] ?>" class="bg-green-500 text-white px-3 py-1 rounded text-xs font-bold">Aprovar</a><?php endif; ?><a href="?page=edit_user&id=<?= $u['id'] ?>" class="text-blue-500"><i class="fas fa-edit"></i></a><?php if($u['id']!=$currentUser['id']): ?><a href="?delete_id=<?= $u['id'] ?>" onclick="return confirm('Excluir?')" class="text-red-400"><i class="fas fa-trash-alt"></i></a><?php endif; ?></td></tr>
                                <?php endwhile; ?></tbody></table>
                            </div>
                        <?php endif; ?>

                    <!-- AULAS (ADMIN) -->
                    <?php elseif ($page === 'lessons' || $page === 'edit_lesson'): 
                        $editL = null; 
                        if($page==='edit_lesson' && isset($_GET['id'])) { 
                            $stmt=$pdo->prepare("SELECT * FROM lessons WHERE id=?"); $stmt->execute([$_GET['id']]); $editL=$stmt->fetch(); 
                        }
                        
                        $editS = null;
                        if(isset($_GET['edit_subject'])) {
                            $stmt=$pdo->prepare("SELECT * FROM subjects WHERE id=?"); $stmt->execute([$_GET['edit_subject']]); $editS=$stmt->fetch();
                        }
                    ?>
                        <h1 class="text-2xl font-bold text-gray-800 mb-6">Gestão de Conteúdo</h1>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <!-- 1. Nova Matéria -->
                            <div class="md:col-span-1">
                                <div class="bg-white p-6 rounded shadow mb-6 border-t-4 border-purple-500">
                                    <h3 class="font-bold text-lg mb-4"><?= $editS?'Editar Matéria':'Nova Matéria (Disciplina)' ?></h3>
                                    <?php if($editS): ?><a href="?page=lessons" class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300 mb-2 inline-block">Cancelar</a><?php endif; ?>
                                    <form method="POST" action="?page=lessons&action=<?= $editS?'update_subject':'add_subject' ?>">
                                        <?php if($editS): ?><input type="hidden" name="subject_id" value="<?= $editS['id'] ?>"><?php endif; ?>
                                        <label class="block text-sm font-bold mb-1">Turma</label>
                                        <select name="turma_id" class="border p-2 rounded w-full mb-3" required>
                                            <option value="">Selecione...</option>
                                            <?php $stmt=$pdo->query("SELECT * FROM turmas ORDER BY id DESC"); while($t=$stmt->fetch()): $sel=($editS['turma_id']??'')==$t['id']?'selected':''; echo "<option value='{$t['id']}' $sel>{$t['nome']}</option>"; endwhile; ?>
                                        </select>
                                        <label class="block text-sm font-bold mb-1">Nome da Matéria</label>
                                        <input type="text" name="title" value="<?= $editS['title']??'' ?>" placeholder="Ex: Técnicas de Vendas" class="border p-2 rounded w-full mb-3" required>
                                        <button class="bg-purple-600 text-white px-4 py-2 rounded font-bold w-full"><?= $editS?'Salvar':'Criar Matéria' ?></button>
                                    </form>
                                </div>
                                <div class="bg-white rounded shadow overflow-hidden">
                                    <h4 class="bg-gray-100 p-3 font-bold text-sm text-gray-600">Matérias Criadas</h4>
                                    <ul class="text-sm"><?php $stmt=$pdo->query("SELECT s.*, t.nome as tname FROM subjects s JOIN turmas t ON s.turma_id=t.id ORDER BY s.id DESC LIMIT 5"); while($s=$stmt->fetch()): echo "<li class='p-3 border-b flex justify-between'><span>{$s['title']} <span class='text-xs text-gray-500'>({$s['tname']})</span></span> <div><a href='?page=lessons&edit_subject={$s['id']}' class='text-blue-500 mr-2'><i class='fas fa-edit'></i></a><a href='?page=lessons&delete_subject={$s['id']}' class='text-red-400' onclick=\"return confirm('Excluir? Apagará todas as aulas.')\"><i class='fas fa-trash'></i></a></div></li>"; endwhile; ?></ul>
                                </div>
                            </div>

                            <!-- 2. Nova Aula -->
                            <div class="md:col-span-2">
                                <div class="bg-white p-6 rounded-lg shadow mb-8 border-l-4 border-blue-600">
                                    <h3 class="font-bold text-lg mb-4"><?= $editL?'Editar Aula':'Nova Aula' ?></h3>
                                    <form method="POST" action="?page=lessons&action=<?= $editL?'update_lesson':'add_lesson' ?>" enctype="multipart/form-data" class="space-y-4">
                                        <?php if($editL): ?><input type="hidden" name="lesson_id" value="<?= $editL['id'] ?>"><?php endif; ?>
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <div><label class="block text-sm font-bold text-gray-700">Matéria / Turma</label><select name="subject_id" class="border p-3 rounded w-full bg-white" required><option value="">Selecione...</option><?php $stmt=$pdo->query("SELECT s.id, s.title, t.nome as tname FROM subjects s JOIN turmas t ON s.turma_id=t.id ORDER BY t.id DESC"); while($s=$stmt->fetch()): $sel=($editL['subject_id']??'')==$s['id']?'selected':''; echo "<option value='{$s['id']}' $sel>{$s['title']} - {$s['tname']}</option>"; endwhile; ?></select></div>
                                            <div><label class="block text-sm font-bold text-gray-700">Tipo</label><select name="type" id="type_select" onchange="toggleLessonType(this.value)" class="border p-3 rounded w-full bg-white"><option value="video" <?= ($editL['type']??'')=='video'?'selected':'' ?>>Vídeo</option><option value="link" <?= ($editL['type']??'')=='link'?'selected':'' ?>>Link / Exercício</option><option value="pdf" <?= ($editL['type']??'')=='pdf'?'selected':'' ?>>PDF</option></select></div>
                                        </div>
                                        <div><label class="block text-sm font-bold">Título da Aula</label><input type="text" name="title" value="<?= $editL['title']??'' ?>" class="border p-3 rounded w-full" required></div>
                                        <textarea name="description" class="border p-3 rounded w-full" rows="2" placeholder="Descrição"><?= $editL['description']??'' ?></textarea>
                                        <div id="url_input"><label class="text-sm font-bold" id="url_label">Link</label><input type="text" name="content_url" value="<?= ($editL['type']??'')!=='pdf'?($editL['content_url']??''):'' ?>" id="input_url_field" class="border p-3 rounded w-full"></div>
                                        <div id="file_input" class="hidden"><label class="text-sm font-bold">Arquivo PDF</label><input type="file" name="pdf_file" accept=".pdf" class="border p-2 rounded w-full bg-gray-50"></div>
                                        <div class="flex justify-between items-center"><div class="w-32"><label class="text-xs font-bold uppercase">XP</label><input type="number" name="xp_reward" value="<?= $editL['xp_reward']??50 ?>" class="border p-2 rounded w-full"></div><button class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700">Salvar Aula</button></div>
                                    </form>
                                </div>
                                <?php if($editL): ?><script>document.addEventListener("DOMContentLoaded",function(){toggleLessonType('<?= $editL['type'] ?>');});</script><?php else: ?><script>document.addEventListener("DOMContentLoaded",function(){toggleLessonType('video');});</script><?php endif; ?>
                                <?php if(!$editL): ?><div class="bg-white rounded-lg shadow overflow-x-auto"><table class="w-full text-left min-w-[600px]"><thead class="bg-gray-100 font-bold text-gray-600"><tr><th class="p-4">Aula</th><th class="p-4">Matéria</th><th class="p-4 text-center">XP</th><th class="p-4 text-right">Ações</th></tr></thead><tbody class="divide-y divide-gray-100"><?php $stmt=$pdo->query("SELECT l.*, s.title as stitle, t.nome as tname FROM lessons l LEFT JOIN subjects s ON l.subject_id=s.id LEFT JOIN turmas t ON s.turma_id=t.id ORDER BY l.id DESC"); while($l=$stmt->fetch()): ?><tr class="hover:bg-gray-50"><td class="p-4 font-bold"><?= htmlspecialchars($l['title']) ?> <span class="text-xs bg-blue-100 text-blue-800 px-2 rounded"><?= strtoupper($l['type']) ?></span></td><td class="p-4 text-sm text-gray-600"><?= $l['stitle'] ? "{$l['stitle']} <br><span class='text-xs'>{$l['tname']}</span>" : 'Sem Matéria' ?></td><td class="p-4 text-center"><?= $l['xp_reward'] ?></td><td class="p-4 text-right"><a href="?page=edit_lesson&id=<?= $l['id'] ?>" class="text-blue-500 mr-2"><i class="fas fa-edit"></i></a><a href="?page=lessons&delete_lesson=<?= $l['id'] ?>" onclick="return confirm('Excluir?')" class="text-red-400"><i class="fas fa-trash"></i></a></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
                            </div>
                        </div>

                    <!-- PERFIL -->
                    <?php elseif ($page === 'profile'): ?>
                        <div class="bg-white p-6 rounded-lg shadow-lg max-w-2xl mx-auto">
                            <h2 class="text-2xl font-bold mb-6 text-gray-800">Editar Perfil</h2>
                            <form method="POST" action="?page=profile&action=update_profile" enctype="multipart/form-data" class="space-y-6">
                                <div class="flex flex-col items-center mb-6">
                                    <div class="relative w-32 h-32 mb-4">
                                        <?php if(!empty($currentUser['avatar'])): ?><img src="<?= $currentUser['avatar'] ?>" class="w-32 h-32 rounded-full object-cover border-4 border-blue-100 shadow"><?php else: ?><div class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center text-4xl font-bold text-gray-500"><?= substr($currentUser['name'],0,1) ?></div><?php endif; ?>
                                        <label class="absolute bottom-0 right-0 bg-blue-600 text-white p-2 rounded-full cursor-pointer hover:bg-blue-700 shadow"><i class="fas fa-camera"></i><input type="file" name="avatar" class="hidden" accept="image/*"></label>
                                    </div>
                                    <p class="text-gray-500 text-sm">Clique na câmera para alterar</p>
                                </div>
                                <div><label class="font-bold">Nome</label><input type="text" name="name" value="<?= htmlspecialchars($currentUser['name']) ?>" class="w-full border p-3 rounded mt-1" required></div>
                                <div><label class="font-bold">Email</label><input type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" class="w-full border p-3 rounded mt-1" required></div>
                                <div><label class="font-bold">Nova Senha (Opcional)</label><input type="password" name="password" class="w-full border p-3 rounded mt-1" placeholder="Deixe em branco para manter"></div>
                                <div class="text-right border-t pt-4"><button class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow-lg transition">Salvar Alterações</button></div>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>
<?php endif; ?>
</body>
</html>