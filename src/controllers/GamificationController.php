<?php
require_once __DIR__ . '/../models/ProgressoModel.php';

class GamificationController {
    private $progressoModel;

    public function __construct() {
        $this->progressoModel = new ProgressoModel();
    }

    public function atribuirPontos($user_id, $conteudo_id) {
        $conteudo = $this->progressoModel->getConteudo($conteudo_id);
        $pontos = $conteudo['pontos'];
        $this->progressoModel->addPontos($user_id, $pontos);
        // Atualizar nível: cada 100 pontos = +1 nível
        $total_pontos = $this->progressoModel->getPontos($user_id);
        $nivel = floor($total_pontos / 100) + 1;
        $this->progressoModel->updateNivel($user_id, $nivel);
    }

    public function checkBadges($user_id) {
        $entregas = $this->progressoModel->countEntregas($user_id);
        if ($entregas === 1) {
            $this->progressoModel->addBadge($user_id, 1);  // Primeira Entrega
        }
        $entregas_no_prazo = $this->progressoModel->countEntregasNoPrazo($user_id);
        if ($entregas_no_prazo >= 5) {
            $this->progressoModel->addBadge($user_id, 2);  // Mestre do Prazo
        }
    }

    public function getAlerts() {
        // API JSON para JS
        header('Content-Type: application/json');
        $alerts = $this->progressoModel->getAlertas($_SESSION['user_id']);
        echo json_encode($alerts);
    }

    public function getRanking($imobiliaria_id = null) {
        return $this->progressoModel->getRanking($imobiliaria_id);
    }
}
?>