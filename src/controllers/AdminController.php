<?php
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/TrilhaModel.php';
require_once __DIR__ . '/../models/ProgressoModel.php';

class AdminController {
    private $userModel, $trilhaModel, $progressoModel;

    public function __construct() {
        $this->userModel = new UserModel();
        $this->trilhaModel = new TrilhaModel();
        $this->progressoModel = new ProgressoModel();
    }

    public function dashboard() {
        $pendentes = $this->userModel->getPendentes();
        require __DIR__ . '/../views/admin/dashboard.php';
    }

    public function aprovarUsuarios() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = $_POST['user_id'];
            $this->userModel->aprovarUser($user_id);
        }
        $pendentes = $this->userModel->getPendentes();
        require __DIR__ . '/../views/admin/aprovar_usuarios.php';
    }

    public function criarTrilha() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $titulo = $_POST['titulo'];
            $descricao = $_POST['descricao'];
            $trilha_id = $this->trilhaModel->createTrilha($titulo, $descricao);
            // Adicionar conteúdos (ex: upload PDF)
            if (isset($_FILES['arquivo'])) {
                $target = 'uploads/' . basename($_FILES['arquivo']['name']);
                move_uploaded_file($_FILES['arquivo']['tmp_name'], $target);
                $this->trilhaModel->addConteudo($trilha_id, $_POST['tipo'], $_POST['titulo_conteudo'], $target, $_POST['deadline'] ?? null, $_POST['pontos']);
            }
        }
        require __DIR__ . '/../views/admin/criar_trilha.php';
    }

    public function ranking() {
        $ranking = $this->progressoModel->getRanking();
        require __DIR__ . '/../views/admin/ranking.php';
    }
}
?>