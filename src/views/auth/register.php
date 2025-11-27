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
    <title>Cadastro - EGI</title>
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1E2A47 0%, #2C3E6F 50%, #4A90E2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 550px;
            width: 100%;
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-logo {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .register-logo h1 {
            font-size: 42px;
            color: #1E2A47;
            margin-bottom: 10px;
        }
        
        .register-logo p {
            color: #666;
            font-size: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4A90E2;
            box-shadow: 0 0 0 3px rgba(74,144,226,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-register {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #28C76F 0%, #1FA65B 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40,199,111,0.3);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #E0E0E0;
        }
        
        .register-footer a {
            color: #4A90E2;
            font-weight: 600;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #F0F8FF;
            border-left: 4px solid #4A90E2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #2C3E6F;
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 30px 20px;
            }
            
            .register-logo h1 {
                font-size: 32px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-logo">
            <h1>EGI</h1>
            <p>Escola de Gestão Imobiliária</p>
        </div>
        
        <div class="info-box">
            ⓘ Seu cadastro será analisado por um administrador antes de ser aprovado.
        </div>
        
        <?php if(isset($_SESSION['errors'])): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php 
                        foreach($_SESSION['errors'] as $error) {
                            echo "<li>{$error}</li>";
                        }
                        unset($_SESSION['errors']);
                    ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo BASE_URL; ?>/index.php?action=do_register" method="POST">
            <div class="form-group">
                <label for="nome">Nome Completo *</label>
                <input type="text" id="nome" name="nome" required 
                       value="<?php echo $_SESSION['old_input']['nome'] ?? ''; ?>"
                       placeholder="Seu nome completo">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo $_SESSION['old_input']['email'] ?? ''; ?>"
                           placeholder="seu@email.com">
                </div>
                
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="tel" id="telefone" name="telefone" 
                           value="<?php echo $_SESSION['old_input']['telefone'] ?? ''; ?>"
                           placeholder="(00) 00000-0000">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="senha">Senha *</label>
                    <input type="password" id="senha" name="senha" required 
                           placeholder="Mínimo 6 caracteres">
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha *</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required 
                           placeholder="Confirme a senha">
                </div>
            </div>
            
            <button type="submit" class="btn-register">Cadastrar</button>
        </form>
        
        <div class="register-footer">
            <p>Já tem uma conta? <a href="<?php echo BASE_URL; ?>/index.php">Faça login</a></p>
        </div>
    </div>
    
    <?php unset($_SESSION['old_input']); ?>
</body>
</html>