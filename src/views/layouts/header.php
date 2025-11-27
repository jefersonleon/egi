<?php
// app/views/admin/dashboard.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8"><title>Admin - EGI</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/egi/public/css/style.css">
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <h2>EGI - Admin</h2>
      <nav>
        <a href="/egi/index.php?route=admin_dashboard">Painel</a>
        <a href="/egi/index.php?route=logout">Sair</a>
      </nav>
    </aside>
    <main class="main">
      <header><h1>Painel do Admin</h1></header>
      <section>
        <h3>Cadastros pendentes</h3>
        <?php if(empty($pending)): ?>
          <p>Sem cadastros pendentes.</p>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Nome</th><th>Email</th><th>Imobiliária</th><th>Ação</th></tr></thead>
            <tbody>
              <?php foreach($pending as $p): ?>
                <tr>
                  <td><?=htmlspecialchars($p['name'])?></td>
                  <td><?=htmlspecialchars($p['email'])?></td>
                  <td><?=htmlspecialchars($p['imobiliaria'])?></td>
                  <td><a class="btn" href="/egi/index.php?route=approve_user&id=<?=$p['id']?>">Aprovar</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
