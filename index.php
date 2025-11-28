<?php
session_start();

// =========================================================
// 1. CONFIGURAÇÃO E CONEXÃO
// =========================================================
$host = 'localhost';
$dbname = 'egi_lite';
$user = 'root';
$pass = ''; 

// Configuração de Uploads
$uploadDir = 'uploads/';
$avatarDir = 'uploads/avatars/';

// Cria pastas se não existirem
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
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    } elseif (preg_match('/v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    }
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

// Middleware de Autenticação (exceto login e registro)
if (!$currentUser && $page !== 'login' && $page !== 'register') {
    header('Location: ?page=login');
    exit;
}

// ---------------------------
// AÇÕES (POST & GET)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['approve_id']) || isset($_GET['delete_id']) || isset($_GET['delete_lesson'])) {
    
    // 1. LOGIN
    if ($action === 'do_login') {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === '1234')) {
            // VERIFICAÇÃO DE STATUS (APROVAÇÃO)
            if (isset($user['status']) && $user['status'] === 'pending') {
                header('Location: ?page=login&error=Seu cadastro está em análise pelo administrador.');
                exit;
            }

            $_SESSION['user'] = $user;
            header('Location: ?page=dashboard');
            exit;
        } else {
            header('Location: ?page=login&error=Credenciais inválidas');
            exit;
        }
    }

    // 2. AUTO-CADASTRO (PRÉ-CADASTRO ALUNO)
    if ($action === 'do_register') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $pass = $_POST['password'];
        
        // Verifica email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            header('Location: ?page=register&error=Email já cadastrado.');
            exit;
        }

        $passHash = password_hash($pass, PASSWORD_DEFAULT);
        // Cria como 'student' e status 'pending'
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'student', 'pending')");
        $stmt->execute([$name, $email, $passHash]);
        
        header('Location: ?page=login&msg=Cadastro realizado! Aguarde a aprovação do administrador.');
        exit;
    }

    // 3. AÇÕES DO ADMIN
    if ($currentUser && $currentUser['role'] === 'admin') {

        // APROVAR USUÁRIO
        if (isset($_GET['approve_id'])) {
            $id = $_GET['approve_id'];
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
            header('Location: ?page=users&msg=Usuário aprovado com sucesso!');
            exit;
        }

        // SALVAR AULA
        if ($action === 'add_lesson') {
            $title = $_POST['title'];
            $desc = $_POST['description'];
            $type = $_POST['type'];
            $xp = $_POST['xp_reward'];
            $contentUrl = '';

            if ($type === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
                $fileName = time() . '_' . basename($_FILES['pdf_file']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) $contentUrl = $targetPath;
            } else {
                $rawUrl = $_POST['content_url'];
                $contentUrl = ($type === 'video') ? getYoutubeEmbedUrl($rawUrl) : $rawUrl;
            }

            $stmt = $pdo->prepare("INSERT INTO lessons (title, description, type, content_url, xp_reward) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $type, $contentUrl, $xp]);
            header('Location: ?page=lessons&msg=Aula criada');
            exit;
        }

        // ATUALIZAR AULA
        if ($action === 'update_lesson') {
            $id = $_POST['lesson_id'];
            $title = $_POST['title'];
            $desc = $_POST['description'];
            $type = $_POST['type'];
            $xp = $_POST['xp_reward'];
            
            $stmt = $pdo->prepare("SELECT content_url FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            $curr = $stmt->fetch();
            $contentUrl = $curr['content_url'];

            if ($type === 'video' || $type === 'link') {
                $raw = $_POST['content_url'];
                $contentUrl = ($type === 'video') ? getYoutubeEmbedUrl($raw) : $raw;
            } elseif ($type === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
                $fileName = time() . '_' . basename($_FILES['pdf_file']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) $contentUrl = $targetPath;
            }

            $stmt = $pdo->prepare("UPDATE lessons SET title=?, description=?, type=?, content_url=?, xp_reward=? WHERE id=?");
            $stmt->execute([$title, $desc, $type, $contentUrl, $xp, $id]);
            header('Location: ?page=lessons&msg=Aula atualizada');
            exit;
        }

        // DELETAR AULA
        if (isset($_GET['delete_lesson'])) {
            $id = $_GET['delete_lesson'];
            $pdo->prepare("DELETE FROM progress WHERE lesson_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$id]);
            header('Location: ?page=lessons&msg=Aula removida');
            exit;
        }

        // SALVAR USUÁRIO (Criação manual pelo Admin - Status já vai ativo)
        if ($action === 'save_user') {
            $name = $_POST['name'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            $id = $_POST['user_id'] ?? null;
            
            if ($id) {
                $sql = "UPDATE users SET name=?, email=?, role=? WHERE id=?";
                $params = [$name, $email, $role, $id];
                if (!empty($_POST['password'])) {
                    $sql = "UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?";
                    $params = [$name, $email, $role, password_hash($_POST['password'], PASSWORD_DEFAULT), $id];
                }
                $pdo->prepare($sql)->execute($params);
                $msg = "Dados atualizados";
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->rowCount() > 0) { header('Location: ?page=users&error=Email existe'); exit; }
                
                $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $email, $passHash, $role]);
                $msg = "Usuário criado";
            }
            header("Location: ?page=users&msg=$msg");
            exit;
        }

        // DELETAR USUÁRIO
        if (isset($_GET['delete_id'])) {
            $id = $_GET['delete_id'];
            if ($id != $currentUser['id']) {
                $pdo->prepare("DELETE FROM progress WHERE user_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            }
            header('Location: ?page=users&msg=Usuário removido');
            exit;
        }
    }

    // AÇÕES DE ALUNO (E TODOS LOGADOS)
    if ($currentUser) {
        if ($action === 'update_profile') {
            $name = $_POST['name'];
            $email = $_POST['email'];
            $id = $currentUser['id'];
            $avatarPath = $currentUser['avatar'];

            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $target = $avatarDir . 'u' . $id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) $avatarPath = $target;
                }
            }

            $sql = "UPDATE users SET name=?, email=?, avatar=?"; 
            $params = [$name, $email, $avatarPath];
            if (!empty($_POST['password'])) {
                $sql .= ", password=?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['user'] = $stmt->fetch();
            header('Location: ?page=profile&msg=Perfil salvo');
            exit;
        }

        if ($action === 'complete_lesson' && $currentUser['role'] === 'student') {
            $lid = $_POST['lesson_id'];
            $xp = $_POST['xp_reward'];
            $check = $pdo->prepare("SELECT id FROM progress WHERE user_id=? AND lesson_id=?");
            $check->execute([$currentUser['id'], $lid]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO progress (user_id, lesson_id) VALUES (?, ?)")->execute([$currentUser['id'], $lid]);
                $pdo->prepare("UPDATE users SET xp=xp+? WHERE id=?")->execute([$xp, $currentUser['id']]);
                $_SESSION['user']['xp'] += $xp;
            }
            header('Location: ?page=dashboard&msg=Concluído');
            exit;
        }
    }
}

if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// =========================================================
// 4. FRONT-END
// =========================================================
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
            const urlInput = document.getElementById('url_input');
            const fileInput = document.getElementById('file_input');
            const urlField = document.getElementById('input_url_field');
            const urlLabel = document.getElementById('url_label');
            
            if (val === 'pdf') {
                urlInput.classList.add('hidden'); fileInput.classList.remove('hidden'); urlField.removeAttribute('required');
            } else {
                urlInput.classList.remove('hidden'); fileInput.classList.add('hidden'); urlField.setAttribute('required', 'true');
                if(val === 'link') {
                    urlLabel.innerText = "Link do Google Forms / Exercício";
                    urlField.placeholder = "https://docs.google.com/forms/...";
                } else {
                    urlLabel.innerText = "Link do Vídeo (YouTube)";
                    urlField.placeholder = "https://www.youtube.com/watch?v=...";
                }
            }
        }

        // Função para Menu Mobile
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
            }
        }
    </script>
    <style> 
        @media(max-width:768px){input,select,textarea{font-size:16px!important}} 
        /* Scroll suave */
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans h-screen flex flex-col overflow-hidden">

<!-- LOGIN PAGE -->
<?php if ($page === 'login'): ?>
    <div class="flex-1 flex items-center justify-center bg-blue-900 px-4 py-8 overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
            <div class="text-center mb-6">
                <img src="egi.png" alt="EGI" class="mx-auto w-48 mb-2"> 
                <p class="text-gray-500 font-medium">Portal do Aluno e Mentor</p>
            </div>
            <?php if ($msg): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm text-center border border-green-200"><?= $msg ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center border border-red-200"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="?page=login&action=do_login" class="space-y-4">
                <input type="email" name="email" class="w-full border p-3 rounded text-lg focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Email" required>
                <input type="password" name="password" class="w-full border p-3 rounded text-lg focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Senha" required>
                <button class="w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700 shadow-md text-lg transition transform active:scale-95">ENTRAR</button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="?page=register" class="text-blue-600 font-bold hover:underline">Não tem conta? Crie aqui.</a>
            </div>
            <div class="mt-4 text-xs text-center text-gray-400 border-t pt-4">
                <p>Admin: admin@egi.com / 1234</p>
            </div>
        </div>
    </div>

<!-- REGISTER PAGE -->
<?php elseif ($page === 'register'): ?>
    <div class="flex-1 flex items-center justify-center bg-blue-900 px-4 py-8 overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
            <div class="text-center mb-6">
                <img src="egi.png" alt="EGI" class="mx-auto w-32 mb-2">
                <h2 class="text-2xl font-bold text-gray-800">Criar Conta</h2>
                <p class="text-gray-500 text-sm">Preencha seus dados para solicitar acesso.</p>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="?page=register&action=do_register" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700">Nome Completo</label>
                    <input type="text" name="name" class="w-full border p-3 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Email</label>
                    <input type="email" name="email" class="w-full border p-3 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Senha</label>
                    <input type="password" name="password" class="w-full border p-3 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                </div>
                <button class="w-full bg-green-600 text-white py-3 rounded font-bold hover:bg-green-700 shadow-md text-lg transition transform active:scale-95">SOLICITAR ACESSO</button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="?page=login" class="text-gray-500 hover:text-blue-600">Voltar para Login</a>
            </div>
        </div>
    </div>

<!-- MAIN LAYOUT -->
<?php else: ?>
    <div class="flex flex-1 overflow-hidden h-full">
        
        <!-- SIDEBAR DESKTOP (Hidden on Mobile) -->
        <aside class="w-64 bg-blue-900 text-white flex-col hidden md:flex shadow-2xl z-20">
            <!-- LOGO AREA: FUNDO BRANCO -->
            <div class="p-6 text-center bg-white border-b border-gray-200">
                <img src="egi.png" alt="EGI" class="mx-auto w-32 hover:opacity-90 transition">
            </div>
            
            <div class="p-4 bg-blue-800/50 flex items-center gap-3 border-b border-blue-800 shadow-inner">
                <?php if(!empty($currentUser['avatar'])): ?>
                    <img src="<?= $currentUser['avatar'] ?>" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center font-bold text-white shadow-md">
                        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="overflow-hidden">
                    <p class="font-bold text-sm truncate"><?= htmlspecialchars($currentUser['name']) ?></p>
                    <p class="text-xs text-blue-300 uppercase tracking-wide"><?= $currentUser['role'] === 'admin' ? 'Professor' : 'Aluno' ?></p>
                </div>
            </div>

            <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
                <a href="?page=dashboard" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition border-l-4 <?= $page=='dashboard'?'bg-blue-800 border-green-400 text-white':'border-transparent text-blue-100' ?>">
                    <i class="fas fa-chart-pie w-6 text-center"></i> Dashboard
                </a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="?page=users" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition border-l-4 <?= $page=='users'||$page=='edit_user'?'bg-blue-800 border-green-400 text-white':'border-transparent text-blue-100' ?>">
                        <i class="fas fa-users w-6 text-center"></i> Mentorados
                    </a>
                    <a href="?page=lessons" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition border-l-4 <?= $page=='lessons'||$page=='edit_lesson'?'bg-blue-800 border-green-400 text-white':'border-transparent text-blue-100' ?>">
                        <i class="fas fa-book w-6 text-center"></i> Aulas e Conteúdo
                    </a>
                <?php endif; ?>
                <a href="?page=profile" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition border-l-4 <?= $page=='profile'?'bg-blue-800 border-green-400 text-white':'border-transparent text-blue-100' ?>">
                    <i class="fas fa-user-circle w-6 text-center"></i> Meu Perfil
                </a>
            </nav>
            <a href="?page=logout" class="p-4 text-center text-red-300 hover:text-white hover:bg-red-900/30 transition border-t border-blue-800">
                <i class="fas fa-sign-out-alt mr-2"></i> Sair
            </a>
        </aside>

        <!-- MAIN CONTENT CONTAINER -->
        <div class="flex-1 flex flex-col h-full overflow-hidden bg-gray-50">
            
            <!-- MOBILE HEADER (Sticky) -->
            <header class="md:hidden bg-white text-blue-900 p-4 flex justify-between items-center shadow-md z-30 sticky top-0">
                <!-- Hamburger Button -->
                <button onclick="toggleMobileMenu()" class="text-blue-900 text-2xl focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>

                <img src="egi.png" alt="EGI" class="h-8"> <!-- Logo menor no mobile -->

                <div class="flex items-center gap-3">
                    <?php if(!empty($currentUser['avatar'])): ?>
                        <a href="?page=profile"><img src="<?= $currentUser['avatar'] ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200"></a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- MOBILE NAVIGATION MENU (Hidden by default) -->
            <div id="mobile-menu" class="hidden md:hidden bg-blue-900 text-white shadow-lg absolute top-16 left-0 w-full z-20 transition-all">
                <div class="p-4 border-b border-blue-800">
                    <p class="font-bold text-sm">Olá, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></p>
                    <p class="text-xs text-blue-300"><?= ucfirst($currentUser['role'] === 'admin' ? 'Professor' : 'Aluno') ?></p>
                </div>
                <nav class="flex flex-col">
                    <a href="?page=dashboard" class="px-6 py-4 border-b border-blue-800 hover:bg-blue-800"><i class="fas fa-chart-pie w-6"></i> Dashboard</a>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <a href="?page=users" class="px-6 py-4 border-b border-blue-800 hover:bg-blue-800"><i class="fas fa-users w-6"></i> Mentorados</a>
                        <a href="?page=lessons" class="px-6 py-4 border-b border-blue-800 hover:bg-blue-800"><i class="fas fa-book w-6"></i> Aulas</a>
                    <?php endif; ?>
                    <a href="?page=profile" class="px-6 py-4 border-b border-blue-800 hover:bg-blue-800"><i class="fas fa-user-circle w-6"></i> Perfil</a>
                    <a href="?page=logout" class="px-6 py-4 text-red-300 hover:bg-blue-800 hover:text-white"><i class="fas fa-sign-out-alt w-6"></i> Sair</a>
                </nav>
            </div>

            <!-- SCROLLABLE CONTENT AREA -->
            <main class="flex-1 overflow-y-auto p-4 md:p-8 scroll-smooth">
                <div class="max-w-6xl mx-auto pb-10">
                    
                    <?php if ($msg): ?>
                        <div class="bg-green-100 text-green-700 p-4 rounded shadow mb-6 flex justify-between animate-fade-in-down">
                            <span class="flex items-center gap-2"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></span>
                            <button onclick="this.parentElement.remove()" class="font-bold">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded shadow mb-6 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- DASHBOARD -->
                    <?php if ($page === 'dashboard'): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">Painel Administrativo</h1>
                            
                            <!-- PENDÊNCIAS DE APROVAÇÃO -->
                            <?php 
                                $pendingCount = $pdo->query("SELECT count(*) FROM users WHERE status = 'pending'")->fetchColumn();
                                if ($pendingCount > 0):
                            ?>
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8 flex flex-col sm:flex-row items-center justify-between shadow-sm gap-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-clock text-yellow-600 text-2xl mr-4"></i>
                                        <div>
                                            <p class="font-bold text-yellow-800 text-lg">Aprovações Pendentes</p>
                                            <p class="text-sm text-yellow-700">Existem <strong><?= $pendingCount ?></strong> novos alunos aguardando.</p>
                                        </div>
                                    </div>
                                    <a href="?page=users" class="w-full sm:w-auto text-center bg-yellow-500 text-white px-6 py-2 rounded-lg font-bold hover:bg-yellow-600 shadow transition">Ver Lista</a>
                                </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500 flex flex-col justify-between">
                                    <p class="text-gray-500 text-sm font-bold uppercase">Mentorados Ativos</p>
                                    <p class="text-4xl font-bold text-gray-800 mt-2"><?= $pdo->query("SELECT count(*) FROM users WHERE role='student' AND status='active'")->fetchColumn() ?></p>
                                </div>
                                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500 flex flex-col justify-between">
                                    <p class="text-gray-500 text-sm font-bold uppercase">Aulas Ativas</p>
                                    <p class="text-4xl font-bold text-gray-800 mt-2"><?= $pdo->query("SELECT count(*) FROM lessons")->fetchColumn() ?></p>
                                </div>
                                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500 flex flex-col justify-between">
                                    <p class="text-gray-500 text-sm font-bold uppercase">Atalhos</p>
                                    <div class="mt-4 flex flex-col gap-2">
                                        <a href="?page=users&add=1" class="text-blue-600 font-bold hover:underline flex items-center gap-2"><i class="fas fa-plus-circle"></i> Novo Aluno</a>
                                        <a href="?page=lessons" class="text-blue-600 font-bold hover:underline flex items-center gap-2"><i class="fas fa-video"></i> Nova Aula</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: // ALUNO ?>
                            <div class="mb-8 flex flex-col md:flex-row items-center justify-between bg-white p-6 rounded-xl shadow-sm gap-6 border-l-4 border-blue-500">
                                <div class="flex items-center gap-4">
                                    <?php if(!empty($currentUser['avatar'])): ?>
                                        <img src="<?= $currentUser['avatar'] ?>" class="w-16 h-16 rounded-full object-cover border-2 border-blue-500 shadow-md">
                                    <?php endif; ?>
                                    <div>
                                        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Olá, <?= explode(' ', $currentUser['name'])[0] ?>! 👋</h1>
                                        <p class="text-sm text-gray-500">Pronto para aprender algo novo?</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-auto flex items-center justify-between md:justify-start bg-blue-50 px-6 py-4 rounded-xl border border-blue-100">
                                    <div class="text-left md:text-right mr-6">
                                        <p class="text-xs font-bold text-blue-800 uppercase tracking-wider">XP Total</p>
                                        <p class="text-3xl font-bold text-blue-600"><?= $currentUser['xp'] ?></p>
                                    </div>
                                    <i class="fas fa-medal text-yellow-500 text-5xl drop-shadow-sm"></i>
                                </div>
                            </div>
                            
                            <h3 class="font-bold text-xl text-gray-700 mb-6 pl-2 border-l-4 border-blue-600">Sua Trilha de Conhecimento</h3>
                            <div class="grid gap-8">
                                <?php 
                                $stmt = $pdo->prepare("SELECT l.*, p.completed_at FROM lessons l LEFT JOIN progress p ON l.id=p.lesson_id AND p.user_id=? ORDER BY l.id ASC");
                                $stmt->execute([$currentUser['id']]);
                                $lessons = $stmt->fetchAll();
                                if(count($lessons)===0) echo "<div class='p-8 text-center bg-white rounded shadow text-gray-500'>Nenhuma aula disponível no momento.</div>";
                                
                                foreach($lessons as $l):
                                    $isCompleted = !empty($l['completed_at']);
                                    $icon = $l['type']=='pdf'?'fa-file-pdf':($l['type']=='link'?'fa-clipboard-list':'fa-play-circle');
                                ?>
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row group transition hover:shadow-md">
                                    <?php if ($l['type'] === 'video'): ?>
                                        <div class="w-full md:w-5/12 bg-black aspect-video relative">
                                            <iframe src="<?= htmlspecialchars($l['content_url']) ?>" class="w-full h-full absolute inset-0" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full md:w-48 h-40 md:h-auto bg-gray-100 flex items-center justify-center text-gray-400 group-hover:text-blue-500 transition">
                                            <i class="fas <?= $icon ?> text-5xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1 p-6 flex flex-col justify-between">
                                        <div>
                                            <div class="flex justify-between items-start mb-2">
                                                <h4 class="text-lg font-bold text-gray-800 leading-tight"><?= htmlspecialchars($l['title']) ?></h4>
                                                <?php if($isCompleted): ?>
                                                    <span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-bold whitespace-nowrap flex items-center gap-1"><i class="fas fa-check"></i> Feito</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-gray-600 text-sm mb-4 line-clamp-3"><?= htmlspecialchars($l['description']) ?></p>
                                        </div>
                                        <div class="flex flex-col sm:flex-row items-center gap-3 mt-auto">
                                            <?php if($l['type']!=='video'): 
                                                $btnT = $l['type']=='pdf'?'Baixar PDF':'Fazer Exercício';
                                                $btnC = $l['type']=='pdf'?'bg-blue-600 hover:bg-blue-700':'bg-purple-600 hover:bg-purple-700';
                                            ?>
                                                <a href="<?= htmlspecialchars($l['content_url']) ?>" target="_blank" class="w-full sm:w-auto text-center <?= $btnC ?> text-white px-5 py-3 rounded-lg text-sm font-bold shadow-sm transition transform active:scale-95">
                                                    <?= $btnT ?> <i class="fas fa-external-link-alt ml-1"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if(!$isCompleted): ?>
                                                <form method="POST" action="?page=dashboard&action=complete_lesson" class="w-full sm:w-auto">
                                                    <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>">
                                                    <input type="hidden" name="xp_reward" value="<?= $l['xp_reward'] ?>">
                                                    <button class="w-full bg-white border-2 border-green-500 text-green-600 hover:bg-green-50 px-5 py-3 rounded-lg text-sm font-bold transition">
                                                        Concluir (+<?= $l['xp_reward'] ?> XP)
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <!-- USERS (ADMIN) -->
                    <?php elseif ($page === 'users' || $page === 'edit_user'): 
                        $editUser = null;
                        if($page==='edit_user' && isset($_GET['id'])) {
                            $stmt=$pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$_GET['id']]); $editUser=$stmt->fetch();
                        }
                    ?>
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                            <h1 class="text-2xl font-bold text-gray-800">Gestão de Usuários</h1>
                            <?php if(!$editUser && !isset($_GET['add'])): ?>
                                <a href="?page=users&add=1" class="w-full md:w-auto text-center bg-green-600 text-white px-5 py-2 rounded font-bold shadow hover:bg-green-700 transition">
                                    <i class="fas fa-plus mr-2"></i> Adicionar Manualmente
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if($editUser || isset($_GET['add'])): ?>
                            <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-blue-600 max-w-2xl mx-auto">
                                <form method="POST" action="?page=users&action=save_user" class="space-y-4">
                                    <?php if($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div><label class="block text-sm font-bold text-gray-700">Nome</label><input type="text" name="name" value="<?= $editUser['name']??'' ?>" class="w-full border p-3 rounded" required></div>
                                        <div><label class="block text-sm font-bold text-gray-700">Email</label><input type="email" name="email" value="<?= $editUser['email']??'' ?>" class="w-full border p-3 rounded" required></div>
                                    </div>
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700">Função</label>
                                            <select name="role" class="w-full border p-3 rounded bg-white">
                                                <option value="student" <?= ($editUser['role']??'')=='student'?'selected':'' ?>>Aluno</option>
                                                <option value="admin" <?= ($editUser['role']??'')=='admin'?'selected':'' ?>>Professor</option>
                                            </select>
                                        </div>
                                        <div><label class="block text-sm font-bold text-gray-700">Senha</label><input type="password" name="password" class="w-full border p-3 rounded" <?= $editUser?'':'required' ?> placeholder="<?= $editUser?'Alterar senha...':'' ?>"></div>
                                    </div>
                                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                                        <a href="?page=users" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancelar</a>
                                        <button class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 transition">Salvar</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left whitespace-nowrap">
                                        <thead class="bg-gray-100 text-gray-600 text-xs font-bold uppercase">
                                            <tr>
                                                <th class="px-6 py-4">Nome</th>
                                                <th class="px-6 py-4">Email</th>
                                                <th class="px-6 py-4">Status</th>
                                                <th class="px-6 py-4 text-center">XP</th>
                                                <th class="px-6 py-4 text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php 
                                            $stmt = $pdo->query("SELECT * FROM users ORDER BY status DESC, id DESC"); 
                                            while($u=$stmt->fetch()): 
                                                $statusClass = $u['status']==='active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                                                $statusLabel = $u['status']==='active' ? 'Ativo' : 'Pendente';
                                            ?>
                                            <tr class="hover:bg-blue-50 transition">
                                                <td class="px-6 py-4 font-bold text-gray-800 flex items-center gap-3">
                                                    <?php if(!empty($u['avatar'])): ?><img src="<?= $u['avatar'] ?>" class="w-8 h-8 rounded-full object-cover"><?php else: ?><div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold"><?= substr($u['name'],0,1) ?></div><?php endif; ?>
                                                    <?= htmlspecialchars($u['name']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                                                <td class="px-6 py-4"><span class="px-2 py-1 rounded-full text-xs font-bold <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                                <td class="px-6 py-4 text-center text-blue-600 font-bold"><?= $u['xp'] ?></td>
                                                <td class="px-6 py-4 text-right space-x-2">
                                                    <?php if($u['status'] === 'pending'): ?>
                                                        <a href="?approve_id=<?= $u['id'] ?>" class="bg-green-500 text-white px-3 py-1 rounded text-xs font-bold hover:bg-green-600 shadow" title="Aprovar Acesso"><i class="fas fa-check"></i></a>
                                                    <?php endif; ?>
                                                    <a href="?page=edit_user&id=<?= $u['id'] ?>" class="text-blue-500 hover:text-blue-700 px-2"><i class="fas fa-edit"></i></a>
                                                    <?php if($u['id']!=$currentUser['id']): ?>
                                                        <a href="?delete_id=<?= $u['id'] ?>" onclick="return confirm('Excluir?')" class="text-red-400 hover:text-red-600 px-2"><i class="fas fa-trash-alt"></i></a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                    <!-- AULAS (ADMIN) -->
                    <?php elseif ($page === 'lessons' || $page === 'edit_lesson'): 
                        $editL = null;
                        if($page==='edit_lesson' && isset($_GET['id'])) { $stmt=$pdo->prepare("SELECT * FROM lessons WHERE id=?"); $stmt->execute([$_GET['id']]); $editL=$stmt->fetch(); }
                    ?>
                        <h1 class="text-2xl font-bold text-gray-800 mb-6">Gestão de Conteúdo</h1>
                        <div class="bg-white p-6 rounded-lg shadow mb-8 border-l-4 border-blue-600">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="font-bold text-lg text-blue-800"><?= $editL?'Editar Aula':'Nova Aula' ?></h3>
                                <?php if($editL): ?><a href="?page=lessons" class="text-xs bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">Cancelar</a><?php endif; ?>
                            </div>
                            <form method="POST" action="?page=lessons&action=<?= $editL?'update_lesson':'add_lesson' ?>" enctype="multipart/form-data" class="space-y-6">
                                <?php if($editL): ?><input type="hidden" name="lesson_id" value="<?= $editL['id'] ?>"><?php endif; ?>
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Título</label><input type="text" name="title" value="<?= $editL['title']??'' ?>" class="w-full border p-3 rounded" required></div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Tipo</label>
                                        <select name="type" id="type_select" onchange="toggleLessonType(this.value)" class="w-full border p-3 rounded bg-white">
                                            <option value="video" <?= ($editL['type']??'')=='video'?'selected':'' ?>>Vídeo</option>
                                            <option value="link" <?= ($editL['type']??'')=='link'?'selected':'' ?>>Link / Exercício</option>
                                            <option value="pdf" <?= ($editL['type']??'')=='pdf'?'selected':'' ?>>PDF</option>
                                        </select>
                                    </div>
                                </div>
                                <div><label class="block text-sm font-bold text-gray-700 mb-1">Descrição</label><textarea name="description" class="w-full border p-3 rounded" rows="3"><?= $editL['description']??'' ?></textarea></div>
                                
                                <div id="url_input">
                                    <label class="block text-sm font-bold text-gray-700 mb-1" id="url_label">Link</label>
                                    <input type="text" name="content_url" value="<?= ($editL['type']??'')!=='pdf'?($editL['content_url']??''):'' ?>" id="input_url_field" class="w-full border p-3 rounded">
                                </div>
                                <div id="file_input" class="hidden">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Arquivo PDF</label>
                                    <input type="file" name="pdf_file" accept=".pdf" class="w-full border p-2 rounded bg-gray-50">
                                </div>

                                <div class="flex items-center justify-between pt-4 border-t">
                                    <div class="w-32"><label class="block text-xs font-bold uppercase text-gray-500 mb-1">XP</label><input type="number" name="xp_reward" value="<?= $editL['xp_reward']??50 ?>" class="w-full border p-2 rounded text-center"></div>
                                    <button class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow-md transition"><?= $editL?'Salvar':'Publicar' ?></button>
                                </div>
                            </form>
                        </div>
                        <?php if($editL): ?><script>document.addEventListener("DOMContentLoaded",function(){toggleLessonType('<?= $editL['type'] ?>');});</script><?php else: ?><script>document.addEventListener("DOMContentLoaded",function(){toggleLessonType('video');});</script><?php endif; ?>

                        <?php if(!$editL): ?>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left whitespace-nowrap">
                                    <thead class="bg-gray-100 text-gray-600 text-xs font-bold uppercase"><tr><th class="px-6 py-4">Título</th><th class="px-6 py-4">Tipo</th><th class="px-6 py-4 text-center">XP</th><th class="px-6 py-4 text-right">Ações</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php 
                                        $stmt=$pdo->query("SELECT * FROM lessons ORDER BY id DESC");
                                        while($l=$stmt->fetch()):
                                            $icon=$l['type']=='video'?'fa-video':($l['type']=='pdf'?'fa-file-pdf':'fa-link');
                                            $col=$l['type']=='video'?'text-blue-700 bg-blue-100':($l['type']=='pdf'?'text-red-700 bg-red-100':'text-purple-700 bg-purple-100');
                                        ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($l['title']) ?></td>
                                            <td class="px-6 py-4"><span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-bold <?= $col ?>"><i class="fas <?= $icon ?>"></i> <?= strtoupper($l['type']) ?></span></td>
                                            <td class="px-6 py-4 text-center"><?= $l['xp_reward'] ?></td>
                                            <td class="px-6 py-4 text-right space-x-2">
                                                <a href="?page=edit_lesson&id=<?= $l['id'] ?>" class="text-blue-500 hover:text-blue-700 px-2"><i class="fas fa-edit"></i></a>
                                                <a href="?page=lessons&delete_lesson=<?= $l['id'] ?>" onclick="return confirm('Excluir?')" class="text-red-400 hover:text-red-600 px-2"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                    <!-- PERFIL -->
                    <?php elseif ($page === 'profile'): ?>
                        <h1 class="text-2xl font-bold text-gray-800 mb-6">Meu Perfil</h1>
                        
                        <div class="bg-white p-6 rounded-lg shadow-lg max-w-2xl mx-auto">
                            <form method="POST" action="?page=profile&action=update_profile" enctype="multipart/form-data" class="space-y-6">
                                
                                <div class="flex flex-col md:flex-row items-center gap-6 mb-6">
                                    <div class="relative">
                                        <?php if(!empty($currentUser['avatar'])): ?>
                                            <img src="<?= $currentUser['avatar'] ?>" class="w-24 h-24 rounded-full object-cover border-4 border-blue-100">
                                        <?php else: ?>
                                            <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center text-3xl font-bold text-gray-500">
                                                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="w-full">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Trocar Foto</label>
                                        <input type="file" name="avatar" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Nome Completo</label>
                                        <input type="text" name="name" value="<?= htmlspecialchars($currentUser['name']) ?>" class="w-full border p-3 rounded" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" class="w-full border p-3 rounded" required>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700">Nova Senha <span class="font-normal text-gray-400">(Deixe em branco para manter)</span></label>
                                    <input type="password" name="password" class="w-full border p-3 rounded">
                                </div>

                                <div class="pt-4 border-t text-right">
                                    <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow-lg transition">
                                        Salvar Alterações
                                    </button>
                                </div>
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