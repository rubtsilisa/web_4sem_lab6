<?php
session_start();
require_once 'config.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['pass'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Заполните логин и пароль';
    } else {
        $stmt = $pdo->prepare("SELECT id, login, password_hash FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="max-width: 450px;">
    <h2>Вход для редактирования данных</h2>
    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Логин:</label>
            <input type="text" name="login" required>
        </div>
        <div class="form-group">
            <label>Пароль:</label>
            <input type="password" name="pass" required>
        </div>
        <button type="submit">Войти</button>
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php">На главную</a>
        </div>
    </form>
</div>
</body>
</html>
