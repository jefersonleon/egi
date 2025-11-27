<?php
function url($page) {
    return "index.php?page=$page";
}
?>
<div class="dashboard">
    <h1>Bem-vindo, <?php echo $_SESSION['nome']; ?></h1>
    <div class="progresso">
        <progress value="<?php echo $progresso['perc']; ?>" max="100"></progress>
        <span>Nível: <?php echo $_SESSION['nivel']; ?> | Pontos: <?php echo $_SESSION['pontos']; ?></span>
    </div>
    <div class="badges">
        <?php foreach ($badges as $badge): ?>
            <img src="<?php echo $badge['icone']; ?>" alt="<?php echo $badge['nome']; ?>">
        <?php endforeach; ?>
    </div>
    <div id="alerts"></div> <!-- Preenchido por JS -->
</div>
<?php
$content = ob_get_clean();
$title = 'Dashboard Mentorado';
require __DIR__ . '/../layouts/main.php';
?>