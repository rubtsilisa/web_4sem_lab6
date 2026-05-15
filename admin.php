<?php

require_once 'config.php';

if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
    send_auth_request();
}

$login = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];

$stmt = $pdo->prepare("SELECT * FROM admins WHERE login = ? AND password_hash = MD5(?)");
$stmt->execute([$login, $password]);
$admin = $stmt->fetch();

if (!$admin) {
    send_auth_request();
}

function send_auth_request() {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    print('<h1>401 Требуется авторизация</h1>');
    print('<p>Для доступа к панели администратора введите логин и пароль.</p>');
    exit();
}

$message = '';
$edit_data = null;

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = '<div class="success-message">Запись успешно удалена</div>';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="error-message">Ошибка удаления: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT a.*, u.login FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $stmt = $pdo->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
        $stmt->execute([$id]);
        $edit_data['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $update_id = (int)$_POST['update_id'];
    $full_name = trim($_POST['fio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = trim($_POST['birthdate'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $selected_languages = $_POST['language'] ?? [];
    $biography = trim($_POST['bio'] ?? '');
    $contract_accepted = isset($_POST['agreement']) ? 1 : 0;
    
    $validation_errors = [];
    
    if (empty($full_name)) {
        $validation_errors[] = 'ФИО обязательно для заполнения';
    } elseif (strlen($full_name) > 150) {
        $validation_errors[] = 'ФИО не может быть длиннее 150 символов';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $full_name)) {
        $validation_errors[] = 'ФИО может содержать только буквы, пробелы и дефисы';
    }
    
    if (empty($phone)) {
        $validation_errors[] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^(\+7|8)\d{10}$/', $phone)) {
        $validation_errors[] = 'Телефон должен быть в формате +7XXXXXXXXXX или 8XXXXXXXXXX';
    }
    
    if (empty($email)) {
        $validation_errors[] = 'Email обязателен для заполнения';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = 'Некорректный формат email';
    }
    
    if (empty($birth_date)) {
        $validation_errors[] = 'Дата рождения обязательна';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $validation_errors[] = 'Неверный формат даты';
    }
    
    if (empty($gender) || !in_array($gender, ['male', 'female'])) {
        $validation_errors[] = 'Выберите пол';
    }
    
    if (empty($selected_languages)) {
        $validation_errors[] = 'Выберите хотя бы один язык программирования';
    }
    
    if (!$contract_accepted) {
        $validation_errors[] = 'Подтвердите ознакомление с контрактом';
    }
    
    if (empty($validation_errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE applications SET 
                full_name = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, biography = ?, contract_accepted = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_accepted, $update_id]);
            
            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$update_id]);
            
            $languages_list = [
                'pascal' => 'Pascal', 'c' => 'C', 'cpp' => 'C++', 'javascript' => 'JavaScript',
                'php' => 'PHP', 'python' => 'Python', 'java' => 'Java', 'haskell' => 'Haskell',
                'clojure' => 'Clojure', 'prolog' => 'Prolog', 'scala' => 'Scala'
            ];
            
            foreach ($selected_languages as $lang_name) {
                if (isset($languages_list[$lang_name])) {
                    $stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                    $stmt->execute([$lang_name]);
                    $lang_id = $stmt->fetchColumn();
                    if ($lang_id) {
                        $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                        $stmt->execute([$update_id, $lang_id]);
                    }
                }
            }
            
            $pdo->commit();
            $message = '<div class="success-message">Запись успешно обновлена</div>';
            $edit_data = null;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="error-message">Ошибка обновления: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="error-message">' . implode('<br>', $validation_errors) . '</div>';
    }
}

$stmt = $pdo->prepare("
    SELECT a.*, u.login 
    FROM applications a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC
");
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT pl.name, COUNT(al.application_id) as count 
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY count DESC, pl.name
");
$stmt->execute();
$language_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_users = count($applications);

$languages_list = [
    'pascal' => 'Pascal', 'c' => 'C', 'cpp' => 'C++', 'javascript' => 'JavaScript',
    'php' => 'PHP', 'python' => 'Python', 'java' => 'Java', 'haskell' => 'Haskell',
    'clojure' => 'Clojure', 'prolog' => 'Prolog', 'scala' => 'Scala'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffe6f0 0%, #ffccdd 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff0f5;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(255, 105, 180, 0.2);
            border: 1px solid #ffb6c1;
        }
        
        h1 {
            color: #d63384;
            margin-bottom: 10px;
            border-bottom: 3px solid #ffb6c1;
            padding-bottom: 10px;
        }
        
        h2 {
            color: #b13e6b;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #ffb6c1;
        }
        
        h3 {
            color: #b13e6b;
            margin-bottom: 15px;
        }
        
        .stats-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #d63384, #b8206a);
            color: white;
            padding: 20px;
            border-radius: 15px;
            min-width: 200px;
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 48px;
            font-weight: bold;
        }
        
        .stat-card .label {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .stats-table {
            background: #ffe0e7;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stats-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .stats-table th, .stats-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ffb6c1;
        }
        
        .stats-table th {
            background: #d63384;
            color: white;
            border-radius: 10px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            overflow-x: auto;
            display: block;
        }
        
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ffb6c1;
        }
        
        .data-table th {
            background: #d63384;
            color: white;
            position: sticky;
            top: 0;
        }
        
        .data-table tr:hover {
            background: #ffe0e7;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            border: none;
        }
        
        .btn-edit {
            background: #4CAF50;
            color: white;
        }
        
        .btn-edit:hover {
            background: #45a049;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #da190b;
        }
        
        .btn-cancel {
            background: #999;
            color: white;
        }
        
        .btn-save {
            background: #d63384;
            color: white;
            padding: 10px 20px;
            font-size: 14px;
        }
        
        .btn-save:hover {
            background: #b8206a;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .edit-form {
            background: #ffe0e7;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid #d63384;
        }
        
        .edit-form h3 {
            margin-bottom: 20px;
            color: #d63384;
        }
        
        .edit-form .form-group {
            margin-bottom: 15px;
        }
        
        .edit-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #b13e6b;
        }
        
        .edit-form input, .edit-form select, .edit-form textarea {
            width: 100%;
            padding: 8px;
            border: 2px solid #ffb6c1;
            border-radius: 12px;
            background: white;
        }
        
        .edit-form input:focus, .edit-form select:focus, .edit-form textarea:focus {
            outline: none;
            border-color: #d63384;
        }
        
        .edit-form select[multiple] {
            height: 100px;
        }
        
        .edit-form .radio-group {
            display: flex;
            gap: 20px;
        }
        
        .edit-form .radio-group input {
            width: auto;
        }
        
        .edit-form .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .edit-form .checkbox-group input {
            width: auto;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-male {
            background: #2196F3;
            color: white;
        }
        
        .badge-female {
            background: #E91E63;
            color: white;
        }
        
        .languages-list {
            font-size: 12px;
        }
        
        .admin-info {
            background: #ffe0e7;
            padding: 10px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: right;
            color: #b13e6b;
        }
        
        .admin-info a {
            color: #d63384;
            text-decoration: none;
        }
        
        .admin-info a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .data-table th, .data-table td {
                padding: 6px;
                font-size: 12px;
            }
            .btn {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-info">
        Вы вошли как администратор | <a href="index.php">Вернуться к форме</a>
    </div>
    
    <h1>Панель администратора</h1>
    <p>Управление данными пользователей</p>
    
    <?= $message ?>
    
    <?php if ($edit_data): ?>
    <div class="edit-form">
        <h3>Редактирование записи #<?= $edit_data['id'] ?> (пользователь: <?= htmlspecialchars($edit_data['login']) ?>)</h3>
        <form method="POST">
            <input type="hidden" name="update_id" value="<?= $edit_data['id'] ?>">
            
            <div class="form-group">
                <label>ФИО:</label>
                <input type="text" name="fio" value="<?= htmlspecialchars($edit_data['full_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Телефон:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($edit_data['phone']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Дата рождения:</label>
                <input type="date" name="birthdate" value="<?= htmlspecialchars($edit_data['birth_date']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Пол:</label>
                <div class="radio-group">
                    <input type="radio" name="gender" value="male" id="edit_male" <?= $edit_data['gender'] == 'male' ? 'checked' : '' ?>>
                    <label for="edit_male">Мужской</label>
                    <input type="radio" name="gender" value="female" id="edit_female" <?= $edit_data['gender'] == 'female' ? 'checked' : '' ?>>
                    <label for="edit_female">Женский</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Языки программирования:</label>
                <select name="language[]" multiple>
                    <?php 
                    $edit_langs = $edit_data['languages'] ?? [];
                    foreach ($languages_list as $value => $label):
                        $selected = in_array($label, $edit_langs) ? 'selected' : '';
                    ?>
                        <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Биография:</label>
                <textarea name="bio" rows="4"><?= htmlspecialchars($edit_data['biography'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="agreement" value="1" id="edit_agreement" <?= $edit_data['contract_accepted'] ? 'checked' : '' ?>>
                <label for="edit_agreement">С контрактом ознакомлен</label>
            </div>
            
            <button type="submit" class="btn btn-save">Сохранить изменения</button>
            <a href="admin.php" class="btn btn-cancel">Отмена</a>
        </form>
    </div>
    <?php endif; ?>
    
    <h2>Статистика</h2>
    <div class="stats-container">
        <div class="stat-card">
            <div class="number"><?= $total_users ?></div>
            <div class="label">Всего пользователей</div>
        </div>
    </div>
    
    <div class="stats-table">
        <h3>Популярность языков программирования</h3>
        <table>
            <thead>
                <tr><th>Язык программирования</th><th>Количество пользователей</th></tr>
            </thead>
            <tbody>
                <?php foreach ($language_stats as $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($stat['name']) ?></td>
                    <td><?= $stat['count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <h2>Все пользователи</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дата рождения</th>
                <th>Пол</th>
                <th>Языки</th>
                <th>Биография</th>
                <th>Контракт</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?= $app['id'] ?></td>
                <td><?= htmlspecialchars($app['login']) ?></td>
                <td><?= htmlspecialchars($app['full_name']) ?></td>
                <td><?= htmlspecialchars($app['phone']) ?></td>
                <td><?= htmlspecialchars($app['email']) ?></td>
                <td><?= htmlspecialchars($app['birth_date']) ?></td>
                <td>
                    <span class="badge <?= $app['gender'] == 'male' ? 'badge-male' : 'badge-female' ?>">
                        <?= $app['gender'] == 'male' ? 'Мужской' : 'Женский' ?>
                    </span>
                </td>
                <td class="languages-list">
                    <?php
                    $stmt = $pdo->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
                    $stmt->execute([$app['id']]);
                    $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo implode(', ', array_map('htmlspecialchars', $langs));
                    ?>
                </td>
                <td><?= htmlspecialchars(substr($app['biography'] ?? '', 0, 50)) ?><?= strlen($app['biography'] ?? '') > 50 ? '...' : '' ?></td>
                <td><?= $app['contract_accepted'] ? 'Да' : 'Нет' ?></td>
                <td>
                    <a href="?edit=<?= $app['id'] ?>" class="btn btn-edit">Ред.</a>
                    <a href="?delete=<?= $app['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить запись #<?= $app['id'] ?>?')">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
