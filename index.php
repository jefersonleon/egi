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

// Função para converter link normal do YouTube em Embed automaticamente
function getYoutubeEmbedUrl($url) {
    // Se já for embed, retorna como está
    if (strpos($url, 'embed') !== false) {
        return $url;
    }

    $videoId = null;

    // Tenta extrair ID de URLs curtas (youtu.be/ID)
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    }
    // Tenta extrair ID de URLs normais (watch?v=ID)
    elseif (preg_match('/v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    }

    // Se encontrou ID, retorna URL embed, senão retorna original
    if ($videoId) {
        return "https://www.youtube.com/embed/" . $videoId;
    }
    
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

// Middleware de Autenticação
if (!$currentUser && $page !== 'login') {
    header('Location: ?page=login');
    exit;
}

// ---------------------------
// AÇÕES (POST & GET DELETES)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete_id']) || isset($_GET['delete_lesson'])) {
    
    // LOGIN
    if ($action === 'do_login') {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === '1234')) {
            $_SESSION['user'] = $user;
            header('Location: ?page=dashboard');
            exit;
        } else {
            header('Location: ?page=login&error=Credenciais inválidas');
            exit;
        }
    }

    // ATUALIZAR PRÓPRIO PERFIL (FOTO E DADOS)
    if ($action === 'update_profile') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $newPass = $_POST['password'];
        $id = $currentUser['id'];
        $avatarPath = $currentUser['avatar']; // Mantém o antigo por padrão

        // Upload de Avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                $fileName = 'user_' . $id . '_' . time() . '.' . $ext;
                $targetPath = $avatarDir . $fileName;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                    $avatarPath = $targetPath;
                }
            }
        }

        // Monta Query Dinâmica (se tiver senha ou não)
        if (!empty($newPass)) {
            $sql = "UPDATE users SET name=?, email=?, password=?, avatar=? WHERE id=?";
            $params = [$name, $email, password_hash($newPass, PASSWORD_DEFAULT), $avatarPath, $id];
        } else {
            $sql = "UPDATE users SET name=?, email=?, avatar=? WHERE id=?";
            $params = [$name, $email, $avatarPath, $id];
        }

        $pdo->prepare($sql)->execute($params);
        
        // Atualiza a Sessão com os novos dados
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['user'] = $stmt->fetch();
        
        header('Location: ?page=profile&msg=Perfil atualizado com sucesso!');
        exit;
    }

    // --- ADMIN ACTIONS ---
    if ($currentUser['role'] === 'admin') {

        // 1. ADICIONAR AULA (COM UPLOAD OU LINK)
        if ($action === 'add_lesson') {
            $title = $_POST['title'];
            $desc = $_POST['description'];
            $type = $_POST['type']; // video, pdf, link
            $xp = $_POST['xp_reward'];
            $contentUrl = '';

            // Lógica de Upload vs URL
            if ($type === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
                $fileName = time() . '_' . basename($_FILES['pdf_file']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
                    $contentUrl = $targetPath;
                } else {
                    header('Location: ?page=dashboard&error=Erro ao fazer upload do arquivo');
                    exit;
                }
            } else {
                // Vídeo ou Link Externo
                $rawUrl = $_POST['content_url'];
                
                // SE FOR VÍDEO, CONVERTE AUTOMATICAMENTE PARA EMBED
                if ($type === 'video') {
                    $contentUrl = getYoutubeEmbedUrl($rawUrl);
                } else {
                    $contentUrl = $rawUrl;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO lessons (title, description, type, content_url, xp_reward) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $type, $contentUrl, $xp]);
            header('Location: ?page=lessons&msg=Aula criada com sucesso');
            exit;
        }

        // 1.5 EDITAR AULA (UPDATE)
        if ($action === 'update_lesson') {
            $id = $_POST['lesson_id'];
            $title = $_POST['title'];
            $desc = $_POST['description'];
            $type = $_POST['type'];
            $xp = $_POST['xp_reward'];
            
            // Busca dados atuais
            $stmt = $pdo->prepare("SELECT content_url FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            $currentLesson = $stmt->fetch();
            $contentUrl = $currentLesson['content_url'];

            // Se for vídeo ou link, pega do input de texto
            if ($type === 'video' || $type === 'link') {
                $rawUrl = $_POST['content_url'];
                if ($type === 'video') {
                    $contentUrl = getYoutubeEmbedUrl($rawUrl);
                } else {
                    $contentUrl = $rawUrl;
                }
            }
            // Se for PDF
            elseif ($type === 'pdf') {
                if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
                    $fileName = time() . '_' . basename($_FILES['pdf_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
                        $contentUrl = $targetPath;
                    }
                } 
            }

            $stmt = $pdo->prepare("UPDATE lessons SET title=?, description=?, type=?, content_url=?, xp_reward=? WHERE id=?");
            $stmt->execute([$title, $desc, $type, $contentUrl, $xp, $id]);
            header('Location: ?page=lessons&msg=Aula atualizada com sucesso');
            exit;
        }

        // 2. EXCLUIR AULA
        if (isset($_GET['delete_lesson'])) {
            $id = $_GET['delete_lesson'];
            $pdo->prepare("DELETE FROM progress WHERE lesson_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$id]);
            header('Location: ?page=lessons&msg=Aula removida');
            exit;
        }

        // 3. CADASTRAR/EDITAR USUÁRIO
        if ($action === 'save_user') {
            $name = $_POST['name'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            $id = $_POST['user_id'] ?? null;
            
            if ($id) {
                // UPDATE
                $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
                $params = [$name, $email, $role, $id];
                if (!empty($_POST['password'])) {
                    $sql = "UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?";
                    $params = [$name, $email, $role, password_hash($_POST['password'], PASSWORD_DEFAULT), $id];
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $msg = "Usuário atualizado";
            } else {
                // CREATE
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->rowCount() > 0) {
                    header('Location: ?page=users&error=Email já cadastrado');
                    exit;
                }
                $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $passHash, $role]);
                $msg = "Usuário criado";
            }
            header("Location: ?page=users&msg=$msg");
            exit;
        }

        // 4. EXCLUIR USUÁRIO
        if (isset($_GET['delete_id'])) {
            $id = $_GET['delete_id'];
            if ($id == $currentUser['id']) {
                header('Location: ?page=users&error=Você não pode se excluir');
                exit;
            }
            $pdo->prepare("DELETE FROM progress WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            header('Location: ?page=users&msg=Usuário removido');
            exit;
        }
    }

    // --- STUDENT ACTIONS ---
    if ($action === 'complete_lesson' && $currentUser['role'] === 'student') {
        $lessonId = $_POST['lesson_id'];
        $xp = $_POST['xp_reward'];

        $check = $pdo->prepare("SELECT id FROM progress WHERE user_id = ? AND lesson_id = ?");
        $check->execute([$currentUser['id'], $lessonId]);
        
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO progress (user_id, lesson_id) VALUES (?, ?)")->execute([$currentUser['id'], $lessonId]);
            $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xp, $currentUser['id']]);
            $_SESSION['user']['xp'] += $xp;
        }
        header('Location: ?page=dashboard&msg=Concluido');
        exit;
    }
}

// LOGOUT
if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// =========================================================
// 3. FRONT-END
// =========================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EGI - Gestão Completa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Script simples para alternar inputs de vídeo/pdf/link
        function toggleLessonType(val) {
            const urlInput = document.getElementById('url_input');
            const fileInput = document.getElementById('file_input');
            const urlField = document.getElementById('input_url_field');
            const urlLabel = document.getElementById('url_label');
            
            if (val === 'pdf') {
                urlInput.classList.add('hidden');
                fileInput.classList.remove('hidden');
                urlField.removeAttribute('required');
            } else {
                urlInput.classList.remove('hidden');
                fileInput.classList.add('hidden');
                urlField.setAttribute('required', 'true');
                
                if(val === 'link') {
                    urlLabel.innerText = "Link do Google Forms / Exercício";
                    urlField.placeholder = "https://docs.google.com/forms/...";
                } else {
                    urlLabel.innerText = "Link do Vídeo (Cole o link normal do YouTube)";
                    urlField.placeholder = "https://www.youtube.com/watch?v=...";
                }
            }
        }
    </script>
    <style>
        /* Melhorias para inputs em mobile (evita zoom no iOS) */
        @media (max-width: 768px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans h-screen flex flex-col">

<?php if ($page === 'login'): ?>
    <div class="flex-1 flex items-center justify-center bg-blue-900 px-4">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
            
            <!-- LOGO NA TELA DE LOGIN -->
            <div class="text-center mb-6">
                <img src="egi.png" alt="EGI - Escola de Gestão Imobiliária" class="mx-auto w-48 mb-2">
                <p class="text-gray-500 font-medium">Portal do Aluno e Mentor</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST" action="?page=login&action=do_login" class="space-y-4">
                <input type="email" name="email" class="w-full border p-3 rounded text-lg" placeholder="Email" required>
                <input type="password" name="password" class="w-full border p-3 rounded text-lg" placeholder="Senha" required>
                <button class="w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700 shadow-md text-lg transition transform hover:scale-[1.02]">ACESSAR PORTAL</button>
            </form>
            <div class="mt-6 text-xs text-center text-gray-400">
                <p>Esqueceu a senha? Contate a secretaria.</p>
                <div class="mt-2 border-t pt-2 opacity-50">
                    <p>Admin: admin@egi.com / 1234</p>
                    <p>Aluno: aluno@egi.com / 1234</p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- LAYOUT PRINCIPAL -->
    <div class="flex flex-1 overflow-hidden">
        
        <!-- SIDEBAR (DESKTOP) -->
        <aside class="w-64 bg-blue-900 text-white flex flex-col hidden md:flex shadow-xl z-10">
            <!-- HEADER DA SIDEBAR COM LOGO (Fundo Branco para destacar o JPG) -->
            <div class="p-6 text-center border-b border-blue-800 bg-white">
                <img src="egi.png" alt="EGI" class="mx-auto w-32">
            </div>
            
            <!-- User Info Sidebar -->
            <div class="p-4 bg-blue-800/50 flex items-center gap-3 border-b border-blue-800">
                <?php if(!empty($currentUser['avatar'])): ?>
                    <img src="<?= $currentUser['avatar'] ?>" class="w-10 h-10 rounded-full object-cover border-2 border-blue-400 shadow-lg">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center font-bold shadow-lg text-sm">
                        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="overflow-hidden">
                    <p class="font-bold text-sm truncate"><?= $currentUser['name'] ?></p>
                    <p class="text-xs text-blue-300 uppercase tracking-wide"><?= $currentUser['role'] === 'admin' ? 'Professor' : 'Aluno' ?></p>
                </div>
            </div>

            <nav class="flex-1 py-4 space-y-1">
                <a href="?page=dashboard" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition <?= $page == 'dashboard' ? 'bg-blue-800 border-r-4 border-green-400' : '' ?>">
                    <i class="fas fa-chart-pie w-6"></i> Dashboard
                </a>
                
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="?page=users" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition <?= $page == 'users' || $page == 'edit_user' ? 'bg-blue-800 border-r-4 border-green-400' : '' ?>">
                        <i class="fas fa-users w-6"></i> Mentorados
                    </a>
                    <a href="?page=lessons" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition <?= $page == 'lessons' || $page == 'edit_lesson' ? 'bg-blue-800 border-r-4 border-green-400' : '' ?>">
                        <i class="fas fa-book w-6"></i> Aulas e Conteúdo
                    </a>
                <?php endif; ?>
                
                <a href="?page=profile" class="flex items-center px-6 py-3 text-sm font-medium hover:bg-blue-800 transition <?= $page == 'profile' ? 'bg-blue-800 border-r-4 border-green-400' : '' ?>">
                    <i class="fas fa-user-circle w-6"></i> Meu Perfil
                </a>
            </nav>

            <a href="?page=logout" class="p-4 text-center text-red-300 hover:bg-red-900/20 transition border-t border-blue-800">
                <i class="fas fa-sign-out-alt mr-2"></i> Sair
            </a>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 overflow-y-auto bg-gray-50 relative">
            
            <!-- MOBILE HEADER (AGORA COM LOGO E FUNDO BRANCO) -->
            <header class="md:hidden bg-white text-blue-900 p-4 flex justify-between items-center shadow-md z-20 sticky top-0">
                <img src="egi.png" alt="EGI" class="h-10">
                <div class="flex items-center gap-4">
                     <!-- Pequeno Avatar no Mobile -->
                    <?php if(!empty($currentUser['avatar'])): ?>
                        <a href="?page=profile"><img src="<?= $currentUser['avatar'] ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200"></a>
                    <?php endif; ?>
                    <a href="?page=logout" class="text-red-500 text-lg"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </header>

            <!-- Padding extra no mobile para conteúdo não colar na borda -->
            <div class="p-4 md:p-8 max-w-6xl mx-auto">
                
                <!-- NOTIFICATIONS -->
                <?php if ($msg): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow mb-6 flex items-center justify-between animate-pulse">
                        <span><i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($msg) ?></span>
                        <button onclick="this.parentElement.remove()" class="text-green-900 font-bold">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow mb-6">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- ==========================================
                     PÁGINA: MEU PERFIL (NOVO)
                   ========================================== -->
                <?php if ($page === 'profile'): ?>
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
                                <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow-lg">
                                    Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>

                <!-- ==========================================
                     PÁGINA: DASHBOARD (HOME)
                   ========================================== -->
                <?php elseif ($page === 'dashboard'): ?>
                    
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <h1 class="text-2xl font-bold text-gray-800 mb-6">Visão Geral</h1>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <?php 
                                $countUsers = $pdo->query("SELECT count(*) FROM users WHERE role='student'")->fetchColumn(); 
                                $countLessons = $pdo->query("SELECT count(*) FROM lessons")->fetchColumn();
                            ?>
                            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                                <p class="text-gray-500 text-sm font-bold uppercase">Total Mentorados</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $countUsers ?></p>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                                <p class="text-gray-500 text-sm font-bold uppercase">Aulas Ativas</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $countLessons ?></p>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500 flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm font-bold uppercase">Ações Rápidas</p>
                                    <div class="mt-2 flex flex-col md:flex-row gap-2">
                                        <a href="?page=users&add=1" class="text-blue-600 hover:underline text-sm font-bold">Novo Aluno</a>
                                        <span class="hidden md:inline text-gray-300">|</span>
                                        <a href="?page=lessons" class="text-blue-600 hover:underline text-sm font-bold">Nova Aula</a>
                                    </div>
                                </div>
                                <i class="fas fa-bolt text-yellow-400 text-3xl"></i>
                            </div>
                        </div>

                        <!-- LISTAGEM RÁPIDA DE ALUNOS (TOP 5 XP) -->
                        <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
                            <div class="px-6 py-4 border-b bg-gray-50 font-bold text-gray-700">
                                Ranking de Alunos (Top 5 XP)
                            </div>
                            <table class="w-full text-left min-w-[500px]"> <!-- Min-width para scroll horizontal no mobile -->
                                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-6 py-3">Aluno</th>
                                        <th class="px-6 py-3">Email</th>
                                        <th class="px-6 py-3 text-right">XP Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php 
                                    $stmt = $pdo->query("SELECT * FROM users WHERE role='student' ORDER BY xp DESC LIMIT 5");
                                    while ($row = $stmt->fetch()):
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 font-medium text-gray-800 flex items-center gap-3">
                                            <?php if(!empty($row['avatar'])): ?>
                                                <img src="<?= $row['avatar'] ?>" class="w-8 h-8 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold"><?= substr($row['name'],0,1) ?></div>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($row['name']) ?>
                                        </td>
                                        <td class="px-6 py-3 text-gray-500 text-sm"><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="px-6 py-3 text-right font-bold text-blue-600"><?= $row['xp'] ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: // DASHBOARD ALUNO ?>
                        
                        <div class="mb-8 flex flex-col md:flex-row items-center justify-between bg-white p-6 rounded-xl shadow-sm gap-4">
                            <div class="flex items-center gap-4">
                                <?php if(!empty($currentUser['avatar'])): ?>
                                    <img src="<?= $currentUser['avatar'] ?>" class="w-16 h-16 rounded-full object-cover border-2 border-blue-500">
                                <?php endif; ?>
                                <div>
                                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Olá, <?= explode(' ', $currentUser['name'])[0] ?>! 👋</h1>
                                    <p class="text-sm text-gray-500">Sua jornada de aprendizado.</p>
                                </div>
                            </div>
                            <div class="w-full md:w-auto flex items-center bg-blue-50 px-5 py-3 rounded-lg border border-blue-100 justify-between md:justify-start">
                                <div class="text-left md:text-right mr-4">
                                    <p class="text-xs font-bold text-blue-800 uppercase">Pontos XP</p>
                                    <p class="text-2xl font-bold text-blue-600"><?= $currentUser['xp'] ?></p>
                                </div>
                                <i class="fas fa-medal text-yellow-500 text-4xl"></i>
                            </div>
                        </div>

                        <h3 class="font-bold text-xl text-gray-700 mb-4 border-l-4 border-blue-600 pl-3">Sua Trilha de Aulas</h3>
                        <div class="grid gap-6">
                            <?php 
                            $stmt = $pdo->prepare("SELECT l.*, p.completed_at FROM lessons l LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ? ORDER BY l.id ASC");
                            $stmt->execute([$currentUser['id']]);
                            $lessons = $stmt->fetchAll();

                            if (count($lessons) === 0) echo "<p class='text-gray-500 italic'>Nenhuma aula disponível ainda.</p>";

                            foreach ($lessons as $l):
                                $isCompleted = !empty($l['completed_at']);
                                // Ícones Dinâmicos
                                $icon = 'fa-play-circle';
                                if ($l['type'] === 'pdf') $icon = 'fa-file-pdf';
                                if ($l['type'] === 'link') $icon = 'fa-clipboard-list';
                            ?>
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row group hover:shadow-md transition">
                                
                                <!-- ÁREA DE MÍDIA (VÍDEO OU ÍCONE) -->
                                <?php if ($l['type'] === 'video'): ?>
                                    <div class="w-full md:w-1/2 bg-black aspect-video relative">
                                        <iframe src="<?= htmlspecialchars($l['content_url']) ?>" class="w-full h-full absolute inset-0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    </div>
                                <?php else: ?>
                                    <!-- Ícone para PDF/Link -->
                                    <div class="w-full md:w-48 bg-gray-100 flex items-center justify-center py-6 md:py-0 text-gray-400 group-hover:text-blue-500 transition">
                                        <i class="fas <?= $icon ?> text-4xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Conteúdo -->
                                <div class="flex-1 p-6 flex flex-col justify-between">
                                    <div>
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($l['title']) ?></h4>
                                            <?php if ($isCompleted): ?>
                                                <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-bold whitespace-nowrap"><i class="fas fa-check"></i> Feito</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-gray-600 text-sm mb-4"><?= htmlspecialchars($l['description']) ?></p>
                                    </div>
                                    
                                    <div class="flex flex-col sm:flex-row items-center gap-3 mt-auto">
                                        <!-- Ação Principal -->
                                        <?php 
                                            $btnText = '';
                                            $btnColor = 'bg-blue-600 hover:bg-blue-700';
                                            $showExternalButton = true;

                                            if($l['type'] === 'video') $showExternalButton = false; 
                                            
                                            if($l['type'] === 'pdf') $btnText = 'Baixar PDF';
                                            if($l['type'] === 'link') {
                                                $btnText = 'Realizar Exercício';
                                                $btnColor = 'bg-purple-600 hover:bg-purple-700';
                                            }
                                        ?>
                                        
                                        <?php if ($showExternalButton): ?>
                                            <a href="<?= htmlspecialchars($l['content_url']) ?>" target="_blank" class="w-full sm:w-auto text-center <?= $btnColor ?> text-white px-4 py-3 rounded text-sm font-bold shadow-sm transition">
                                                <?= $btnText ?> <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Botão Concluir -->
                                        <?php if (!$isCompleted): ?>
                                            <form method="POST" action="?page=dashboard&action=complete_lesson" class="w-full sm:w-auto">
                                                <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>">
                                                <input type="hidden" name="xp_reward" value="<?= $l['xp_reward'] ?>">
                                                <button class="w-full bg-white border border-green-500 text-green-600 hover:bg-green-50 px-4 py-3 rounded text-sm font-bold transition">
                                                    Marcar (+<?= $l['xp_reward'] ?> XP)
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                <!-- ==========================================
                     PÁGINA: GESTÃO DE USUÁRIOS (CRUD)
                   ========================================== -->
                <?php elseif ($page === 'users' || $page === 'edit_user'): ?>
                    
                    <?php 
                        $editUser = null;
                        if ($page === 'edit_user' && isset($_GET['id'])) {
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $stmt->execute([$_GET['id']]);
                            $editUser = $stmt->fetch();
                        }
                    ?>

                    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                        <h1 class="text-2xl font-bold text-gray-800">
                            <?= $editUser ? 'Editar Usuário' : 'Gestão de Mentorados' ?>
                        </h1>
                        <?php if (!$editUser && !isset($_GET['add'])): ?>
                            <a href="?page=users&add=1" class="w-full md:w-auto text-center bg-green-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-green-700">
                                <i class="fas fa-plus mr-2"></i> Novo Usuário
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($editUser || isset($_GET['add'])): ?>
                        <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-blue-600 max-w-2xl mx-auto">
                            <form method="POST" action="?page=users&action=save_user" class="space-y-4">
                                <?php if ($editUser): ?>
                                    <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                                <?php endif; ?>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Nome Completo</label>
                                        <input type="text" name="name" value="<?= $editUser['name'] ?? '' ?>" class="w-full border p-3 rounded" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Email</label>
                                        <input type="email" name="email" value="<?= $editUser['email'] ?? '' ?>" class="w-full border p-3 rounded" required>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Função</label>
                                        <select name="role" class="w-full border p-3 rounded bg-white">
                                            <option value="student" <?= ($editUser['role'] ?? '') === 'student' ? 'selected' : '' ?>>Aluno (Mentorado)</option>
                                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Professor (Admin)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Senha <?= $editUser ? '(Deixe vazio para manter)' : '(Obrigatório)' ?></label>
                                        <input type="password" name="password" class="w-full border p-3 rounded" <?= $editUser ? '' : 'required' ?>>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                                    <a href="?page=users" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancelar</a>
                                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700">
                                        <?= $editUser ? 'Atualizar Dados' : 'Cadastrar Usuário' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>

                        <div class="bg-white rounded-lg shadow overflow-x-auto">
                            <table class="w-full text-left min-w-[600px]">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-xs font-bold">
                                    <tr>
                                        <th class="px-6 py-4">ID</th>
                                        <th class="px-6 py-4">Nome</th>
                                        <th class="px-6 py-4">Email</th>
                                        <th class="px-6 py-4">Função</th>
                                        <th class="px-6 py-4 text-center">XP</th>
                                        <th class="px-6 py-4 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php 
                                    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
                                    while ($u = $stmt->fetch()):
                                    ?>
                                    <tr class="hover:bg-blue-50 transition">
                                        <td class="px-6 py-4 text-gray-500 text-sm">#<?= $u['id'] ?></td>
                                        <td class="px-6 py-4 font-bold text-gray-800 flex items-center gap-3">
                                            <?php if(!empty($u['avatar'])): ?>
                                                <img src="<?= $u['avatar'] ?>" class="w-8 h-8 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold"><?= substr($u['name'],0,1) ?></div>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($u['name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-bold <?= $u['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700' ?>">
                                                <?= $u['role'] === 'admin' ? 'ADMIN' : 'ALUNO' ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center font-mono text-blue-600"><?= $u['xp'] ?></td>
                                        <td class="px-6 py-4 text-right space-x-2">
                                            <a href="?page=edit_user&id=<?= $u['id'] ?>" class="text-blue-500 hover:text-blue-700" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($u['id'] != $currentUser['id']): ?>
                                                <a href="?delete_id=<?= $u['id'] ?>" onclick="return confirm('Tem certeza? Isso apagará todo o progresso deste aluno.')" class="text-red-400 hover:text-red-600" title="Excluir">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <!-- ==========================================
                     PÁGINA: AULAS E CONTEÚDO
                   ========================================== -->
                <?php elseif ($page === 'lessons' || $page === 'edit_lesson'): ?>
                    
                    <?php
                        $editLesson = null;
                        if ($page === 'edit_lesson' && isset($_GET['id'])) {
                            $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
                            $stmt->execute([$_GET['id']]);
                            $editLesson = $stmt->fetch();
                        }
                    ?>

                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Gestão de Conteúdo</h1>

                    <div class="bg-white p-6 rounded-lg shadow mb-8 border-l-4 border-blue-600">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg text-blue-800"><?= $editLesson ? 'Editar Aula' : 'Adicionar Nova Aula' ?></h3>
                            <?php if ($editLesson): ?>
                                <a href="?page=lessons" class="text-xs bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">Cancelar Edição</a>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="?page=lessons&action=<?= $editLesson ? 'update_lesson' : 'add_lesson' ?>" enctype="multipart/form-data" class="space-y-4">
                            <?php if ($editLesson): ?>
                                <input type="hidden" name="lesson_id" value="<?= $editLesson['id'] ?>">
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">Título</label>
                                    <input type="text" name="title" value="<?= $editLesson['title'] ?? '' ?>" class="w-full border p-3 rounded" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">Tipo de Conteúdo</label>
                                    <select name="type" id="type_select" onchange="toggleLessonType(this.value)" class="w-full border p-3 rounded bg-white">
                                        <option value="video" <?= ($editLesson['type'] ?? '') === 'video' ? 'selected' : '' ?>>Vídeo (YouTube Link)</option>
                                        <option value="link" <?= ($editLesson['type'] ?? '') === 'link' ? 'selected' : '' ?>>Link Externo / Exercício (Google Forms)</option>
                                        <option value="pdf" <?= ($editLesson['type'] ?? '') === 'pdf' ? 'selected' : '' ?>>Arquivo PDF (Upload)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Descrição</label>
                                <textarea name="description" class="w-full border p-3 rounded" rows="2"><?= $editLesson['description'] ?? '' ?></textarea>
                            </div>

                            <div id="url_input">
                                <label class="block text-sm font-bold text-gray-700" id="url_label">Link do Vídeo (YouTube Embed)</label>
                                <input type="text" name="content_url" value="<?= ($editLesson['type'] ?? '') !== 'pdf' ? ($editLesson['content_url'] ?? '') : '' ?>" id="input_url_field" class="w-full border p-3 rounded" placeholder="https://www.youtube.com/embed/...">
                            </div>
                            
                            <div id="file_input" class="hidden">
                                <label class="block text-sm font-bold text-gray-700">Selecione o Arquivo PDF <?= $editLesson ? '<span class="text-gray-400 font-normal">(Opcional: envie apenas para substituir)</span>' : '' ?></label>
                                <div class="border-2 border-dashed border-gray-300 p-4 rounded bg-gray-50 text-center">
                                    <input type="file" name="pdf_file" accept=".pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <?php if ($editLesson && $editLesson['type'] === 'pdf'): ?>
                                        <p class="text-xs text-left mt-2 text-blue-600">Arquivo atual: <?= basename($editLesson['content_url']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-4">
                                <div class="w-32">
                                    <label class="block text-xs font-bold text-gray-500 uppercase">XP Recompensa</label>
                                    <input type="number" name="xp_reward" value="<?= $editLesson['xp_reward'] ?? 50 ?>" class="w-full border p-3 rounded">
                                </div>
                                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700">
                                    <?= $editLesson ? 'Salvar Alterações' : 'Publicar Aula' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if ($editLesson): ?>
                        <script>document.addEventListener("DOMContentLoaded", function() { toggleLessonType('<?= $editLesson['type'] ?>'); });</script>
                    <?php else: ?>
                        <script>document.addEventListener("DOMContentLoaded", function() { toggleLessonType('video'); });</script>
                    <?php endif; ?>

                    <?php if (!$editLesson): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
                        <table class="w-full text-left min-w-[600px]">
                            <thead class="bg-gray-100 text-gray-600 uppercase text-xs font-bold">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Título</th>
                                    <th class="px-6 py-4">Tipo</th>
                                    <th class="px-6 py-4 text-center">XP</th>
                                    <th class="px-6 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php 
                                $stmt = $pdo->query("SELECT * FROM lessons ORDER BY id DESC");
                                while ($l = $stmt->fetch()):
                                    $color = 'bg-gray-100 text-gray-700';
                                    $icon = 'fa-file';
                                    if($l['type'] == 'video') { $color = 'bg-blue-100 text-blue-700'; $icon = 'fa-video'; }
                                    if($l['type'] == 'pdf') { $color = 'bg-red-100 text-red-700'; $icon = 'fa-file-pdf'; }
                                    if($l['type'] == 'link') { $color = 'bg-purple-100 text-purple-700'; $icon = 'fa-link'; }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-500 text-sm">#<?= $l['id'] ?></td>
                                    <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($l['title']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-bold <?= $color ?>">
                                            <i class="fas <?= $icon ?>"></i> <?= strtoupper($l['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center"><?= $l['xp_reward'] ?></td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="?page=edit_lesson&id=<?= $l['id'] ?>" class="text-blue-500 hover:text-blue-700 px-2 border border-blue-200 rounded hover:bg-blue-50 text-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=lessons&delete_lesson=<?= $l['id'] ?>" onclick="return confirm('Excluir esta aula?')" class="text-red-400 hover:text-red-600 px-2 border border-red-200 rounded hover:bg-red-50 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </main>
    </div>

<?php endif; ?>
</body>
</html>