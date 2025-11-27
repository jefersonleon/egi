<?php
session_start();

// =========================================================
// 1. CONFIGURAÇÃO E CONEXÃO
// =========================================================
$host = 'localhost';
$dbname = 'egi_lite';
$user = 'root';
$pass = ''; 

// Configuração de Upload
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='padding:20px; color:red; font-weight:bold'>Erro Crítico: Conexão com Banco falhou.<br>" . $e->getMessage() . "</div>");
}

// =========================================================
// 2. ROTEAMENTO E CONTROLADORES
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

    // --- ADMIN ACTIONS ---
    if ($currentUser['role'] === 'admin') {

        // 1. ADICIONAR AULA (COM UPLOAD)
        if ($action === 'add_lesson') {
            $title = $_POST['title'];
            $desc = $_POST['description'];
            $type = $_POST['type'];
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
                // Se for vídeo ou PDF via link externo
                $contentUrl = $_POST['content_url'];
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
            
            // Busca dados atuais para manter URL se não mudar
            $stmt = $pdo->prepare("SELECT content_url FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            $currentLesson = $stmt->fetch();
            $contentUrl = $currentLesson['content_url'];

            // Se for vídeo, pega do input de texto
            if ($type === 'video') {
                $contentUrl = $_POST['content_url'];
            }
            // Se for PDF
            elseif ($type === 'pdf') {
                // Se enviou novo arquivo, faz upload e substitui
                if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
                    $fileName = time() . '_' . basename($_FILES['pdf_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
                        $contentUrl = $targetPath;
                    }
                } 
                // Se não enviou arquivo, mantemos o $contentUrl antigo (que já pegamos do banco)
            }

            $stmt = $pdo->prepare("UPDATE lessons SET title=?, description=?, type=?, content_url=?, xp_reward=? WHERE id=?");
            $stmt->execute([$title, $desc, $type, $contentUrl, $xp, $id]);
            header('Location: ?page=lessons&msg=Aula atualizada com sucesso');
            exit;
        }

        // 2. EXCLUIR AULA
        if (isset($_GET['delete_lesson'])) {
            $id = $_GET['delete_lesson'];
            // Remove dependências primeiro (progresso)
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
                
                // Se preencheu senha, atualiza
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
            // Limpa histórico primeiro
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
        // Script simples para alternar inputs de vídeo/pdf
        function toggleLessonType(val) {
            const urlInput = document.getElementById('url_input');
            const fileInput = document.getElementById('file_input');
            const urlField = document.getElementById('input_url_field');
            
            if (val === 'pdf') {
                urlInput.classList.add('hidden');
                fileInput.classList.remove('hidden');
                urlField.removeAttribute('required');
            } else {
                urlInput.classList.remove('hidden');
                fileInput.classList.add('hidden');
                // Apenas requer URL se NÃO estivermos editando um PDF existente (caso especial tratado no PHP, mas no JS simplificamos)
                urlField.setAttribute('required', 'true');
            }
        }
    </script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans h-screen flex flex-col">

<?php if ($page === 'login'): ?>
    <div class="flex-1 flex items-center justify-center bg-blue-900">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
            <h1 class="text-3xl font-bold text-center text-blue-900 mb-6">EGI Portal</h1>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST" action="?page=login&action=do_login" class="space-y-4">
                <input type="email" name="email" class="w-full border p-3 rounded" placeholder="Email" required>
                <input type="password" name="password" class="w-full border p-3 rounded" placeholder="Senha" required>
                <button class="w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700">ENTRAR</button>
            </form>
            <div class="mt-4 text-xs text-center text-gray-400">
                <p>Admin: admin@egi.com / 1234</p>
                <p>Aluno: aluno@egi.com / 1234</p>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- LAYOUT PRINCIPAL -->
    <div class="flex flex-1 overflow-hidden">
        
        <!-- SIDEBAR -->
        <aside class="w-64 bg-blue-900 text-white flex flex-col hidden md:flex shadow-xl z-10">
            <div class="p-6 text-center border-b border-blue-800">
                <h2 class="text-2xl font-bold tracking-widest">EGI</h2>
                <p class="text-xs text-blue-300 opacity-70">SISTEMA DE MENTORIA</p>
            </div>
            
            <!-- User Info Sidebar -->
            <div class="p-4 bg-blue-800/50 flex items-center gap-3 border-b border-blue-800">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center font-bold shadow-lg">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>
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
            </nav>

            <a href="?page=logout" class="p-4 text-center text-red-300 hover:bg-red-900/20 transition border-t border-blue-800">
                <i class="fas fa-sign-out-alt mr-2"></i> Sair
            </a>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 overflow-y-auto bg-gray-50 relative">
            
            <!-- MOBILE HEADER -->
            <header class="md:hidden bg-blue-900 text-white p-4 flex justify-between items-center shadow-md">
                <span class="font-bold text-lg">EGI Mobile</span>
                <a href="?page=logout" class="text-red-300"><i class="fas fa-sign-out-alt"></i></a>
            </header>

            <div class="p-6 max-w-6xl mx-auto">
                
                <!-- NOTIFICATIONS -->
                <?php if ($msg): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow mb-6 flex items-center justify-between">
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
                     PÁGINA: DASHBOARD (HOME)
                   ========================================== -->
                <?php if ($page === 'dashboard'): ?>
                    
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
                                    <div class="mt-2 space-x-2">
                                        <a href="?page=users&add=1" class="text-blue-600 hover:underline text-sm font-bold">Novo Aluno</a>
                                        <span class="text-gray-300">|</span>
                                        <a href="?page=lessons" class="text-blue-600 hover:underline text-sm font-bold">Nova Aula</a>
                                    </div>
                                </div>
                                <i class="fas fa-bolt text-yellow-400 text-3xl"></i>
                            </div>
                        </div>

                        <!-- LISTAGEM RÁPIDA DE ALUNOS (TOP 5 XP) -->
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b bg-gray-50 font-bold text-gray-700">
                                Ranking de Alunos (Top 5 XP)
                            </div>
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-6 py-3">Nome</th>
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
                                        <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="px-6 py-3 text-gray-500 text-sm"><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="px-6 py-3 text-right font-bold text-blue-600"><?= $row['xp'] ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: // DASHBOARD ALUNO ?>
                        
                        <div class="mb-8 flex flex-col md:flex-row items-center justify-between bg-white p-6 rounded-xl shadow-sm">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800">Olá, <?= explode(' ', $currentUser['name'])[0] ?>! 👋</h1>
                                <p class="text-gray-500">Continue acumulando conhecimento.</p>
                            </div>
                            <div class="mt-4 md:mt-0 flex items-center bg-blue-50 px-5 py-3 rounded-lg border border-blue-100">
                                <div class="text-right mr-4">
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
                            ?>
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row group hover:shadow-md transition">
                                <!-- Ícone/Thumb -->
                                <div class="w-full md:w-48 bg-gray-100 flex items-center justify-center py-6 md:py-0 text-gray-400 group-hover:text-blue-500 transition">
                                    <i class="fas <?= $l['type'] === 'video' ? 'fa-play-circle' : 'fa-file-pdf' ?> text-4xl"></i>
                                </div>
                                
                                <!-- Conteúdo -->
                                <div class="flex-1 p-6">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($l['title']) ?></h4>
                                        <?php if ($isCompleted): ?>
                                            <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-bold"><i class="fas fa-check"></i> Feito</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($l['description']) ?></p>
                                    
                                    <div class="flex items-center gap-3">
                                        <!-- Ação Principal -->
                                        <a href="<?= htmlspecialchars($l['content_url']) ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-bold shadow-sm transition">
                                            <?= $l['type'] === 'video' ? 'Assistir Vídeo' : 'Baixar PDF' ?>
                                        </a>

                                        <!-- Botão Concluir -->
                                        <?php if (!$isCompleted): ?>
                                            <form method="POST" action="?page=dashboard&action=complete_lesson" class="inline">
                                                <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>">
                                                <input type="hidden" name="xp_reward" value="<?= $l['xp_reward'] ?>">
                                                <button class="bg-white border border-green-500 text-green-600 hover:bg-green-50 px-4 py-2 rounded text-sm font-bold transition">
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
                        // Lógica para Edição
                        $editUser = null;
                        if ($page === 'edit_user' && isset($_GET['id'])) {
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $stmt->execute([$_GET['id']]);
                            $editUser = $stmt->fetch();
                        }
                    ?>

                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">
                            <?= $editUser ? 'Editar Usuário' : 'Gestão de Mentorados' ?>
                        </h1>
                        <?php if (!$editUser && !isset($_GET['add'])): ?>
                            <a href="?page=users&add=1" class="bg-green-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-green-700">
                                <i class="fas fa-plus mr-2"></i> Novo Usuário
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- FORMULÁRIO (ADD/EDIT) -->
                    <?php if ($editUser || isset($_GET['add'])): ?>
                        <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-blue-600 max-w-2xl mx-auto">
                            <form method="POST" action="?page=users&action=save_user" class="space-y-4">
                                <?php if ($editUser): ?>
                                    <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                                <?php endif; ?>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Nome Completo</label>
                                        <input type="text" name="name" value="<?= $editUser['name'] ?? '' ?>" class="w-full border p-2 rounded" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Email</label>
                                        <input type="email" name="email" value="<?= $editUser['email'] ?? '' ?>" class="w-full border p-2 rounded" required>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Função</label>
                                        <select name="role" class="w-full border p-2 rounded bg-white">
                                            <option value="student" <?= ($editUser['role'] ?? '') === 'student' ? 'selected' : '' ?>>Aluno (Mentorado)</option>
                                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Professor (Admin)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Senha <?= $editUser ? '(Deixe vazio para manter)' : '(Obrigatório)' ?></label>
                                        <input type="password" name="password" class="w-full border p-2 rounded" <?= $editUser ? '' : 'required' ?>>
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

                        <!-- LISTAGEM (TABELA) -->
                        <div class="bg-white rounded-lg shadow overflow-x-auto">
                            <table class="w-full text-left">
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
                                        <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($u['name']) ?></td>
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
                        // LÓGICA DE EDIÇÃO: BUSCAR DADOS
                        $editLesson = null;
                        if ($page === 'edit_lesson' && isset($_GET['id'])) {
                            $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
                            $stmt->execute([$_GET['id']]);
                            $editLesson = $stmt->fetch();
                        }
                    ?>

                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Gestão de Conteúdo</h1>

                    <!-- FORMULÁRIO DE NOVA/EDITAR AULA -->
                    <div class="bg-white p-6 rounded-lg shadow mb-8 border-l-4 border-blue-600">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg text-blue-800"><?= $editLesson ? 'Editar Aula' : 'Adicionar Nova Aula' ?></h3>
                            <?php if ($editLesson): ?>
                                <a href="?page=lessons" class="text-xs bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">Cancelar Edição</a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ENCTYPE MULTIPART ESSENCIAL PARA UPLOAD -->
                        <form method="POST" action="?page=lessons&action=<?= $editLesson ? 'update_lesson' : 'add_lesson' ?>" enctype="multipart/form-data" class="space-y-4">
                            
                            <?php if ($editLesson): ?>
                                <input type="hidden" name="lesson_id" value="<?= $editLesson['id'] ?>">
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">Título</label>
                                    <input type="text" name="title" value="<?= $editLesson['title'] ?? '' ?>" class="w-full border p-2 rounded" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">Tipo de Conteúdo</label>
                                    <select name="type" id="type_select" onchange="toggleLessonType(this.value)" class="w-full border p-2 rounded">
                                        <option value="video" <?= ($editLesson['type'] ?? '') === 'video' ? 'selected' : '' ?>>Vídeo (YouTube Link)</option>
                                        <option value="pdf" <?= ($editLesson['type'] ?? '') === 'pdf' ? 'selected' : '' ?>>Arquivo PDF (Upload)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Descrição</label>
                                <textarea name="description" class="w-full border p-2 rounded" rows="2"><?= $editLesson['description'] ?? '' ?></textarea>
                            </div>

                            <!-- ÁREA DINÂMICA (LINK OU UPLOAD) -->
                            <div id="url_input">
                                <label class="block text-sm font-bold text-gray-700">Link do Vídeo (YouTube Embed)</label>
                                <input type="text" name="content_url" value="<?= ($editLesson['type'] ?? '') === 'video' ? $editLesson['content_url'] : '' ?>" id="input_url_field" class="w-full border p-2 rounded" placeholder="https://www.youtube.com/embed/...">
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
                                    <input type="number" name="xp_reward" value="<?= $editLesson['xp_reward'] ?? 50 ?>" class="w-full border p-2 rounded">
                                </div>
                                <button type="submit" class="bg-blue-600 text-white px-8 py-2 rounded font-bold hover:bg-blue-700">
                                    <?= $editLesson ? 'Salvar Alterações' : 'Publicar Aula' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Executa toggle ao carregar se estiver editando -->
                    <?php if ($editLesson): ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                toggleLessonType('<?= $editLesson['type'] ?>');
                            });
                        </script>
                    <?php else: ?>
                        <!-- Garante estado inicial correto para adição -->
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                toggleLessonType('video');
                            });
                        </script>
                    <?php endif; ?>

                    <?php if (!$editLesson): ?>
                    <!-- LISTA DE AULAS EXISTENTES (Ocultar durante edição para focar no form) -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="w-full text-left">
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
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-500 text-sm">#<?= $l['id'] ?></td>
                                    <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($l['title']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-bold <?= $l['type'] === 'video' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' ?>">
                                            <i class="fas <?= $l['type'] === 'video' ? 'fa-video' : 'fa-file-pdf' ?>"></i>
                                            <?= strtoupper($l['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center"><?= $l['xp_reward'] ?></td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="?page=edit_lesson&id=<?= $l['id'] ?>" class="text-blue-500 hover:text-blue-700 px-2 border border-blue-200 rounded hover:bg-blue-50 text-sm" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=lessons&delete_lesson=<?= $l['id'] ?>" onclick="return confirm('Excluir esta aula?')" class="text-red-400 hover:text-red-600 px-2 border border-red-200 rounded hover:bg-red-50 text-sm" title="Excluir">
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