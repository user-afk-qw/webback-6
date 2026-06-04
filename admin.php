<?php
/**
 * Задание 6: Страница администратора с HTTP-авторизацией
 * - Просмотр всех данных пользователей
 * - Редактирование данных
 * - Удаление записей
 * - Статистика по языкам программирования
 */

// Подключение к БД
$db_user = 'u82292';
$db_pass = '7009026';
$db_name = 'u82292';

try {
    $db = new PDO("mysql:host=localhost;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// HTTP-авторизация
$auth_required = true;
$admin_login = 'admin';
$admin_password = 'admin123'; // Измените на свой пароль

// Проверяем HTTP-авторизацию
if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
    $auth_required = true;
} else {
    // Проверяем в БД
    $stmt = $db->prepare("SELECT password_hash FROM admin WHERE login = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
        $auth_required = false;
    } else {
        $auth_required = true;
    }
}

if ($auth_required) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel - Webback 3"');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>401 Требуется авторизация</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 50px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            h1 { font-size: 48px; margin-bottom: 20px; }
            p { font-size: 18px; }
        </style>
    </head>
    <body>
        <h1>🔐 401 Требуется авторизация</h1>
        <p>Для доступа к панели администратора введите логин и пароль</p>
        <p><small>Логин: admin</small></p>
    </body>
    </html>';
    exit();
}

// Обработка действий
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Удаление записи
if ($action == 'delete' && $id > 0) {
    try {
        // Удаляем связанные языки
        $db->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
        // Удаляем заявку
        $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        $success = "✅ Запись #$id успешно удалена";
    } catch(PDOException $e) {
        $error = "❌ Ошибка при удалении: " . $e->getMessage();
    }
}

// Редактирование записи
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit' && $id > 0) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $biography = trim($_POST['biography'] ?? '');
    $contract_agreed = isset($_POST['contract_agreed']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];
    
    // Валидация
    $errors = [];
    
    if (empty($full_name) || !preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]{2,150}$/u', $full_name)) {
        $errors[] = 'Неверный формат ФИО';
    }
    if (empty($phone) || !preg_match('/^[\+\d\s\(\)\-]{10,20}$/', $phone)) {
        $errors[] = 'Неверный формат телефона';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Неверный формат email';
    }
    if (empty($birth_date)) {
        $errors[] = 'Дата рождения обязательна';
    }
    if (empty($gender) || !in_array($gender, ['male', 'female'])) {
        $errors[] = 'Неверный пол';
    }
    if (empty($languages)) {
        $errors[] = 'Выберите хотя бы один язык';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Обновляем основную запись
            $stmt = $db->prepare("UPDATE applications SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, contract_agreed=? WHERE id=?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_agreed, $id]);
            
            // Удаляем старые языки
            $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$id]);
            
            // Вставляем новые языки
            $langStmt = $db->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $insertLang = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            
            foreach ($languages as $langName) {
                $langStmt->execute([$langName]);
                $lang = $langStmt->fetch();
                if ($lang) {
                    $insertLang->execute([$id, $lang['id']]);
                }
            }
            
            $db->commit();
            $success = "✅ Запись #$id успешно обновлена";
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "❌ Ошибка при обновлении: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Получаем все заявки для отображения
$applications = [];
$stmt = $db->query("SELECT * FROM applications ORDER BY id DESC");
$applications = $stmt->fetchAll();

// Для каждой заявки получаем языки
foreach ($applications as &$app) {
    $langStmt = $db->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
    $langStmt->execute([$app['id']]);
    $app['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Статистика по языкам
$stats = [];
$stmt = $db->query("
    SELECT pl.name, COUNT(al.application_id) as count 
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY count DESC
");
$stats = $stmt->fetchAll();

$totalApplications = count($applications);

// Редактирование - получаем данные для формы
$editData = null;
if ($action == 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
    
    if ($editData) {
        $langStmt = $db->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
        $langStmt->execute([$id]);
        $editData['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Задание 6</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
        }
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex: 1;
            min-width: 200px;
            text-align: center;
        }
        .stat-card h3 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .stat-card p {
            color: #666;
        }
        /* Таблица */
        .table-container {
            background: white;
            border-radius: 15px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .languages-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .lang-badge {
            background: #e8f4fd;
            color: #2196F3;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-edit, .btn-delete {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit {
            background: #2196F3;
            color: white;
        }
        .btn-edit:hover {
            background: #1976D2;
        }
        .btn-delete {
            background: #f44336;
            color: white;
        }
        .btn-delete:hover {
            background: #d32f2f;
        }
        .btn-back {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
        }
        /* Форма редактирования */
        .edit-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .edit-form h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn-save {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-cancel {
            background: #999;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-left: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        /* График статистики */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            flex: 1;
            min-width: 150px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-name {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 30px;
            overflow: hidden;
            margin: 10px 0;
        }
        .stat-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 14px;
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            th, td {
                padding: 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 Панель администратора</h1>
            <p>Управление данными пользователей | <?= date('d.m.Y H:i:s') ?></p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="stats-bar">
            <div class="stat-card">
                <h3><?= $totalApplications ?></h3>
                <p>Всего анкет</p>
            </div>
            <div class="stat-card">
                <h3><?= count($stats) ?></h3>
                <p>Языков программирования</p>
            </div>
            <div class="stat-card">
                <h3><?= $totalApplications > 0 ? round(array_sum(array_column($stats, 'count')) / $totalApplications, 1) : 0 ?></h3>
                <p>Среднее языков на анкету</p>
            </div>
        </div>

        <!-- Форма редактирования -->
        <?php if ($editData): ?>
        <div class="edit-form">
            <h2>✏️ Редактирование записи #<?= $id ?></h2>
            <form method="POST" action="?action=edit&id=<?= $id ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>ФИО</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($editData['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($editData['phone']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($editData['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Дата рождения</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($editData['birth_date']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Пол</label>
                        <select name="gender" required>
                            <option value="male" <?= $editData['gender'] == 'male' ? 'selected' : '' ?>>Мужской</option>
                            <option value="female" <?= $editData['gender'] == 'female' ? 'selected' : '' ?>>Женский</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Языки программирования (Ctrl+клик)</label>
                        <select name="languages[]" multiple required size="5">
                            <?php foreach ($allLanguages as $lang): ?>
                                <option value="<?= $lang ?>" <?= in_array($lang, $editData['languages']) ? 'selected' : '' ?>><?= $lang ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" rows="4"><?= htmlspecialchars($editData['biography']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="contract_agreed" value="1" <?= $editData['contract_agreed'] ? 'checked' : '' ?>>
                        Согласен с контрактом
                    </label>
                </div>
                <button type="submit" class="btn-save">💾 Сохранить изменения</button>
                <a href="admin.php" class="btn-cancel">Отмена</a>
            </form>
        </div>
        <?php endif; ?>

        <!-- Таблица с данными -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Любимые ЯП</th>
                        <th>Контракт</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">Нет данных</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                            <td><?= htmlspecialchars($app['phone']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td><?= date('d.m.Y', strtotime($app['birth_date'])) ?></td>
                            <td><?= $app['gender'] == 'male' ? 'М' : 'Ж' ?></td>
                            <td>
                                <div class="languages-list">
                                    <?php foreach ($app['languages'] as $lang): ?>
                                        <span class="lang-badge"><?= htmlspecialchars($lang) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><?= $app['contract_agreed'] ? '✅' : '❌' ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($app['created_at'])) ?></td>
                            <td class="action-buttons">
                                <a href="?action=edit&id=<?= $app['id'] ?>" class="btn-edit">✏️ Ред.</a>
                                <a href="?action=delete&id=<?= $app['id'] ?>" class="btn-delete" 
                                   onclick="return confirm('Удалить запись #<?= $app['id'] ?>?')">🗑️ Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Статистика по языкам -->
        <div class="header" style="margin-top: 20px;">
            <h2>📊 Статистика по языкам программирования</h2>
            <p>Количество пользователей, выбравших каждый язык</p>
        </div>
        
        <div class="stats-container">
            <?php 
            $maxCount = !empty($stats) ? max(array_column($stats, 'count')) : 1;
            foreach ($stats as $stat): 
                $percent = $maxCount > 0 ? ($stat['count'] / $maxCount) * 100 : 0;
            ?>
                <div class="stat-item">
                    <div class="stat-name"><?= htmlspecialchars($stat['name']) ?></div>
                    <div class="stat-bar">
                        <div class="stat-fill" style="width: <?= $percent ?>%">
                            <?= $stat['count'] ?>
                        </div>
                    </div>
                    <small><?= $totalApplications > 0 ? round(($stat['count'] / $totalApplications) * 100, 1) : 0 ?>% от всех анкет</small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Ссылки -->
        <div style="margin-top: 30px; text-align: center; padding: 20px;">
            <a href="index.php" style="color: #667eea;">← Вернуться к форме регистрации</a>
            |
            <a href="logout.php" style="color: #c33;">Выйти из системы</a>
        </div>
    </div>
</body>
</html>