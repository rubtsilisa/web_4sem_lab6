<?php
session_start();
require_once 'config.php';

$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_login = $_SESSION['login'] ?? null;

$messages = [];
$errors = [];
$form_data = [];

if (isset($_COOKIE['form_data'])) {
    $form_data = json_decode($_COOKIE['form_data'], true);
    if (!is_array($form_data)) {
        $form_data = [];
    }
}

if (isset($_COOKIE['form_errors'])) {
    $errors = json_decode($_COOKIE['form_errors'], true);
    if (!is_array($errors)) {
        $errors = [];
    }
    setcookie('form_errors', '', time() - 3600, '/');
}

if ($current_user_id) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    $user_db_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_db_data) {
        $stmt = $pdo->prepare("SELECT pl.name FROM application_languages al 
                                JOIN programming_languages pl ON al.language_id = pl.id
                                WHERE al.application_id = ?");
        $stmt->execute([$user_db_data['id']]);
        $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $form_data = [
            'fio' => $user_db_data['full_name'],
            'phone' => $user_db_data['phone'],
            'email' => $user_db_data['email'],
            'birthdate' => $user_db_data['birth_date'],
            'gender' => $user_db_data['gender'],
            'language' => $langs,
            'bio' => $user_db_data['biography'],
            'agreement' => $user_db_data['contract_accepted']
        ];
    }
}

if (isset($_COOKIE['save_success'])) {
    $messages[] = '<div class="success-message">Данные успешно сохранены!</div>';
    setcookie('save_success', '', time() - 3600, '/');
}

$show_credentials = false;
$generated_login = '';
$generated_pass = '';

if (isset($_COOKIE['generated_login']) && isset($_COOKIE['generated_pass'])) {
    $show_credentials = true;
    $generated_login = htmlspecialchars($_COOKIE['generated_login']);
    $generated_pass = htmlspecialchars($_COOKIE['generated_pass']);
    setcookie('generated_login', '', time() - 3600, '/');
    setcookie('generated_pass', '', time() - 3600, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $full_name = isset($_POST['fio']) ? trim($_POST['fio']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $birth_date = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $selected_languages = isset($_POST['language']) ? $_POST['language'] : [];
    $biography = isset($_POST['bio']) ? trim($_POST['bio']) : '';
    $contract_accepted = isset($_POST['agreement']) ? true : false;
    
    $validation_errors = [];
    
    if (empty($full_name)) {
        $validation_errors['fio'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($full_name) > 150) {
        $validation_errors['fio'] = 'ФИО не может быть длиннее 150 символов';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $full_name)) {
        $validation_errors['fio'] = 'ФИО может содержать только буквы, пробелы и дефисы';
    }
    
    if (empty($phone)) {
        $validation_errors['phone'] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^(\+7|8)\d{10}$/', $phone)) {
        $validation_errors['phone'] = 'Телефон должен быть в формате +7XXXXXXXXXX или 8XXXXXXXXXX';
    }
    
    if (empty($email)) {
        $validation_errors['email'] = 'Email обязателен для заполнения';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = 'Некорректный формат email';
    }
    
    if (empty($birth_date)) {
        $validation_errors['birthdate'] = 'Дата рождения обязательна для заполнения';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $validation_errors['birthdate'] = 'Неверный формат даты. Используйте формат ГГГГ-ММ-ДД';
    } else {
        $date_parts = explode('-', $birth_date);
        if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
            $validation_errors['birthdate'] = 'Указана несуществующая дата';
        }
    }
    
    $allowed_genders = ['male', 'female'];
    if (empty($gender)) {
        $validation_errors['gender'] = 'Пожалуйста, выберите ваш пол';
    } elseif (!in_array($gender, $allowed_genders)) {
        $validation_errors['gender'] = 'Выбрано некорректное значение пола';
    }
    
    if (empty($selected_languages)) {
        $validation_errors['language'] = 'Пожалуйста, выберите хотя бы один язык программирования';
    }
    
    if (!empty($biography) && strlen($biography) > 5000) {
        $validation_errors['bio'] = 'Биография не может быть длиннее 5000 символов';
    }
    
    if (!$contract_accepted) {
        $validation_errors['agreement'] = 'Необходимо подтвердить, что вы ознакомлены с контрактом';
    }
    
    if (!empty($validation_errors)) {
        setcookie('form_errors', json_encode($validation_errors), 0, '/');
        setcookie('form_data', json_encode($_POST), 0, '/');
        header('Location: index.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($current_user_id) {
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ?");
            $stmt->execute([$current_user_id]);
            $app_id = $stmt->fetchColumn();
            
            if ($app_id) {
                $sql = "UPDATE applications SET 
                            full_name = :full_name,
                            phone = :phone,
                            email = :email,
                            birth_date = :birth_date,
                            gender = :gender,
                            biography = :biography,
                            contract_accepted = :contract_accepted
                        WHERE user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':birth_date' => $birth_date,
                    ':gender' => $gender,
                    ':biography' => $biography,
                    ':contract_accepted' => $contract_accepted ? 1 : 0,
                    ':user_id' => $current_user_id
                ]);
                
                $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
                $stmt->execute([$app_id]);
                $application_id = $app_id;
            } else {
                $sql = "INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_accepted ? 1 : 0, $current_user_id]);
                $application_id = $pdo->lastInsertId();
            }
        } else {
            $login = 'user_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $plain_password = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
            $stmt->execute([$login, $password_hash]);
            $new_user_id = $pdo->lastInsertId();
            
            $sql = "INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_accepted ? 1 : 0, $new_user_id]);
            $application_id = $pdo->lastInsertId();
            
            setcookie('generated_login', $login, time() + 3600, '/');
            setcookie('generated_pass', $plain_password, time() + 3600, '/');
        }
        
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
                    $stmt->execute([$application_id, $lang_id]);
                }
            }
        }
        
        $pdo->commit();
        
        setcookie('form_data', '', time() - 3600, '/');
        setcookie('form_errors', '', time() - 3600, '/');
        setcookie('save_success', '1', time() + 60, '/');
        
        header('Location: index.php');
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $validation_errors['database'] = 'Ошибка базы данных: ' . $e->getMessage();
        setcookie('form_errors', json_encode($validation_errors), 0, '/');
        setcookie('form_data', json_encode($_POST), 0, '/');
        header('Location: index.php');
        exit;
    }
}

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
    <title>Форма с авторизацией</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="auth-info">
        <?php if ($current_user_id): ?>
            Вы вошли как <strong><?= htmlspecialchars($current_user_login) ?></strong>
            <a href="login.php?action=logout">Выйти</a>
        <?php else: ?>
            <a href="login.php">Войти</a>
        <?php endif; ?>
    </div>
    
    <h2>Анкета</h2>
    
    <?php foreach ($messages as $msg): echo $msg; endforeach; ?>
    
    <?php if ($show_credentials): ?>
    <div class="credentials">
        <strong>Ваши данные для входа (сохраните их!):</strong><br>
        Логин: <strong><?= $generated_login ?></strong><br>
        Пароль: <strong><?= $generated_pass ?></strong>
    </div>
    <?php endif; ?>
    
    <?php if (isset($errors['database'])): ?>
        <div class="error-message"><?= htmlspecialchars($errors['database']) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="fio">ФИО:</label>
            <input type="text" id="fio" name="fio" 
                   value="<?= isset($form_data['fio']) ? htmlspecialchars($form_data['fio']) : '' ?>"
                   class="<?= isset($errors['fio']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['fio'])): ?>
                <div class="error-message"><?= $errors['fio'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?= isset($form_data['phone']) ? htmlspecialchars($form_data['phone']) : '' ?>"
                   class="<?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-message"><?= $errors['phone'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" 
                   value="<?= isset($form_data['email']) ? htmlspecialchars($form_data['email']) : '' ?>"
                   class="<?= isset($errors['email']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-message"><?= $errors['email'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="birthdate">Дата рождения:</label>
            <input type="date" id="birthdate" name="birthdate" 
                   value="<?= isset($form_data['birthdate']) ? htmlspecialchars($form_data['birthdate']) : '' ?>"
                   class="<?= isset($errors['birthdate']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['birthdate'])): ?>
                <div class="error-message"><?= $errors['birthdate'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <input type="radio" id="male" name="gender" value="male"
                       <?= (isset($form_data['gender']) && $form_data['gender'] == 'male') ? 'checked' : '' ?>
                       class="<?= isset($errors['gender']) ? 'field-error' : '' ?>">
                <label for="male">Мужской</label>
                
                <input type="radio" id="female" name="gender" value="female"
                       <?= (isset($form_data['gender']) && $form_data['gender'] == 'female') ? 'checked' : '' ?>
                       class="<?= isset($errors['gender']) ? 'field-error' : '' ?>">
                <label for="female">Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-message"><?= $errors['gender'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="language">Любимый язык программирования:</label>
            <select id="language" name="language[]" multiple size="5"
                    class="<?= isset($errors['language']) ? 'field-error' : '' ?>">
                <?php foreach ($languages_list as $value => $label): ?>
                    <?php
                    $selected_langs = isset($form_data['language']) ? (array)$form_data['language'] : [];
                    $selected = in_array($value, $selected_langs) ? 'selected' : '';
                    ?>
                    <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['language'])): ?>
                <div class="error-message"><?= $errors['language'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="bio">Биография:</label>
            <textarea id="bio" name="bio" rows="5"
                      class="<?= isset($errors['bio']) ? 'field-error' : '' ?>"><?= isset($form_data['bio']) ? htmlspecialchars($form_data['bio']) : '' ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="error-message"><?= $errors['bio'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group checkbox-wrapper">
            <input type="checkbox" id="agreement" name="agreement" value="1"
                   <?= isset($form_data['agreement']) && $form_data['agreement'] ? 'checked' : '' ?>
                   class="<?= isset($errors['agreement']) ? 'field-error' : '' ?>">
            <label for="agreement">С контрактом ознакомлен(а)</label>
            <?php if (isset($errors['agreement'])): ?>
                <div class="error-message"><?= $errors['agreement'] ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <button type="submit">Сохранить</button>
        </div>
    </form>
</div>
</body>
</html>
