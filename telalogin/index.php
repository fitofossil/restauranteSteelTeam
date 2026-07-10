<?php
session_start();

$usuarioCorreto = "admin";
$senhaCorreta = "123456";

$erro = "";

if(isset($_POST['entrar'])){

    $usuario = trim($_POST['usuario']);
    $senha = trim($_POST['senha']);

    if($usuario == $usuarioCorreto && $senha == $senhaCorreta){

        $_SESSION['usuario'] = $usuario;

        header("Location: admin.php");
        exit();

    }else{

        $erro = "Usuário ou senha incorretos!";

    }

}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dogão Lanches - Login</title>

<link rel="stylesheet" href="telalogin.css">

</head>

<body>

<div class="container">

    <div class="placa">

        <h1>🌭 Dogão Lanches</h1>

        <form method="POST">

            <input
            type="text"
            name="usuario"
            placeholder="Usuário"
            required>

            <input
            type="password"
            name="senha"
            placeholder="Senha"
            required>

            <button type="submit" name="entrar">
                Entrar
            </button>

        </form>

        <?php
        if($erro != ""){
            echo "<p class='erro'>$erro</p>";
        }
        ?>

    </div>

</div>

</body>
</html>