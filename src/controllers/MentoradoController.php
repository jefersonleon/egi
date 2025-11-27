<?php
require_once __DIR__ . '/../models/TrilhaModel.php';
require_once __DIR__ . '/../models/ProgressoModel.php';
require_once __DIR__ . '/../controllers/GamificationController.php';

class MentoradoController {
    private $trilhaModel, $progressoModel, $gamController;

    public function __construct() {
        $this->trilhaModel = new TrilhaModel();
        $this->progressoModel = new ProgressoModel();
        $this->gamController = new GamificationController();
    }

    public function dashboard() {
        $progresso = $this->progressoModel->getProgresso($_SESSION['user_id']);
        $badges = $this->progressoModel->getBadges($_SESSION['user_id']);
        require __DIR__ . '/../views/mentorado/dashboard.php';
    }

    public function trilha() {
        $trilhas = $this->trilhaModel->getTrilhas();
        require __DIR__ . '/../views/mentorado/trilha.php';
    }

    public function calendario() {
        $deadlines = $this->trilhaModel->getDeadlines();
        require __DIR__ . '/../views/mentorado/calendario.php';
    }

    public function ranking() {
        $ranking = $this->progressoModel->getRanking($_SESSION['imobiliaria_id']);  // Interno à imobiliária
        require __DIR__ . '/../views/mentorado/ranking.php';
    }

    public function concluirConteudo() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $conteudo_id = $_POST['conteudo_id'];
            $entrega = $_POST['entrega'] ?? null;
            $this->progressoModel->concluirConteudo($_SESSION['user_id'], $conteudo_id, $entrega);
            $this->gamController->atribuirPontos($_SESSION['user_id'], $conteudo_id);
            $this->gamController->checkBadges($_SESSION['user_id']);
        }
    }
}
?>