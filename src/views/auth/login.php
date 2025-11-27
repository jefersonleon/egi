<?php
function url($page) {
    return "index.php?page=$page";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EGI - Login</title>
    <style>
        body { font-family: Arial; background: #007bff; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin:0; }
        .box { background: white; color: #333; padding: 40px; border-radius: 15px; width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        input, button { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 6px; }
        button { background: #007bff; color: white; font-size: 16px; cursor: pointer; }
        h2 { text-align: center; color: #007bff; }
        a { color: #007bff; text-align: center; display: block; margin-top: 15px; }
    </style>
</head>
<body>
<div class="box">
    <h2>EGI - Escola de Gestão Imobiliária</h2>
    <?php if (isset($_SESSION['erro'])): ?>
        <p style="color:red; text-align:center"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></p>
    <?php endif; ?>
    <form method="POST" action="<?= url('auth/login') ?>">
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Entrar</button>
    </form>
    <a href="<?= url('auth/register') ?>">Criar conta (mentorado)</a>
</div>
</body>
</html>