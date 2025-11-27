<?php
require_once __DIR__ . '/../models/UserModel.php';

class AuthController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $senha = $_POST['senha'] ?? '';

            $user = $this->userModel->getUserByEmail($email);

            if ($user && password_verify($senha, $user['senha'])) {
                if ($user['aprovado'] || $user['tipo'] === 'admin') {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['tipo']       = $user['tipo'];
                    $_SESSION['aprovado']   = (bool)$user['aprovado'];
                    $_SESSION['nome']       = $user['nome'];        // importante pro dashboard
                    $_SESSION['nivel']      = $user['nivel'] ?? 1;
                    $_SESSION['pontos']     = $user['pontos'] ?? 0;

                    header('Location: index.php?page=' . $user['tipo'] . '/dashboard');
                    exit;
                } else {
                    $erro = 'Aguardando aprovação do administrador.';
                }
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        }

        // Só chega aqui se não logou ou deu erro
        require __DIR__ . '/../views/auth/login.php';
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $senha = password_hash($_POST['senha'], PASSWORD_BCRYPT);
            $imobiliaria_id = $_POST['imobiliaria_id'];  // Select de imobiliarias existentes
            $this->userModel->createUser($nome, $email, $senha, 'mentorado', $imobiliaria_id);
            echo 'Cadastro realizado. Aguarde aprovação.';
            header('Refresh: 3; url=/egi/public/auth/login');
        }
        require __DIR__ . '/../views/auth/register.php';
    }

    public function logout()
    {
        session_destroy();
        header('Location: index.php?page=auth/login');
        exit;
    }
}
