<?php
// Админ-панель с авторизацией через сессию
session_start();

// Проверяем авторизацию
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Подключение к БД
try {
    $db = new PDO("mysql:host=localhost;dbname=u82292;charset=utf8", 'u82292', '7009026');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

// Удаление записи
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
    header('Location: admin.php');
    exit;
}

// Редактирование
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $birth_date = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $biography = $_POST['biography'];
    $contract = isset($_POST['contract']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];
    
    $db->beginTransaction();
    $stmt = $db->prepare("UPDATE applications SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, contract_agreed=? WHERE id=?");
    $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract, $id]);
    
    $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$id]);
    $langStmt = $db->prepare("SELECT id FROM programming_languages WHERE name=?");
    $insertLang = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?,?)");
    
    foreach ($languages as $lang) {
        $langStmt->execute([$lang]);
        $langData = $langStmt->fetch();
        if ($langData) $insertLang->execute([$id, $langData['id']]);
    }
    $db->commit();
    header('Location: admin.php');
    exit;
}

// Получение данных
$applications = $db->query("SELECT * FROM applications ORDER BY id DESC")->fetchAll();
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM applications WHERE id=?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
    if ($editData) {
        $stmt = $db->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id=pl.id WHERE al.application_id=?");
        $stmt->execute([$id]);
        $editData['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Статистика по языкам
$stats = $db->query("
    SELECT pl.name, COUNT(al.application_id) as cnt
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id ORDER BY cnt DESC
")->fetchAll();

foreach ($applications as &$app) {
    $stmt = $db->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id=pl.id WHERE al.application_id=?");
    $stmt->execute([$app['id']]);
    $app['langs'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1300px; margin: 0 auto; background: white; border-radius: 15px; padding: 25px; }
        h1 { color: #667eea; margin-bottom: 10px; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 12px; min-width: 150px; }
        .stat-card h3 { font-size: 32px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f5f5f5; }
        .lang-badge { background: #e8f4fd; color: #2196F3; padding: 2px 8px; border-radius: 12px; font-size: 12px; display: inline-block; margin: 2px; }
        .btn-edit, .btn-delete { padding: 5px 10px; text-decoration: none; border-radius: 5px; font-size: 12px; margin: 0 2px; display: inline-block; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        .btn-save { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .edit-form { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        .stat-bar { background: #e0e0e0; border-radius: 10px; height: 25px; margin: 5px 0; }
        .stat-fill { background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; text-align: right; padding-right: 8px; color: white; border-radius: 10px; line-height: 25px; }
        .back-link { margin-top: 20px; display: inline-block; color: #667eea; margin-right: 15px; }
        .logout { color: #c33; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👑 Админ-панель</h1>
        <p>Вы вошли как: <strong><?= htmlspecialchars($_SESSION['admin_login'] ?? 'admin') ?></strong> 
        <a href="admin_logout.php" style="color:#c33; margin-left:15px;">🚪 Выйти</a></p>
        
        <div class="stats">
            <div class="stat-card"><h3><?= count($applications) ?></h3><p>Всего анкет</p></div>
            <div class="stat-card"><h3><?= count($stats) ?></h3><p>Языков</p></div>
        </div>
        
        <?php if ($editData): ?>
        <div class="edit-form">
            <h3>✏️ Редактирование записи #<?= $editData['id'] ?></h3>
            <form method="POST" action="?edit=<?= $editData['id'] ?>">
                <div class="form-row">
                    <div class="form-group"><label>ФИО</label><input type="text" name="full_name" value="<?= htmlspecialchars($editData['full_name']) ?>" required></div>
                    <div class="form-group"><label>Телефон</label><input type="tel" name="phone" value="<?= htmlspecialchars($editData['phone']) ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($editData['email']) ?>" required></div>
                    <div class="form-group"><label>Дата рождения</label><input type="date" name="birth_date" value="<?= $editData['birth_date'] ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Пол</label><select name="gender">
                        <option value="male" <?= $editData['gender']=='male'?'selected':'' ?>>Мужской</option>
                        <option value="female" <?= $editData['gender']=='female'?'selected':'' ?>>Женский</option>
                    </select></div>
                    <div class="form-group"><label>Языки (Ctrl для выбора нескольких)</label>
                    <select name="languages[]" multiple size="5">
                        <?php foreach($allLanguages as $lang): ?>
                            <option value="<?=$lang?>" <?= in_array($lang, $editData['languages'])?'selected':'' ?>><?=$lang?></option>
                        <?php endforeach; ?>
                    </select></div>
                </div>
                <div class="form-group"><label>Биография</label><textarea name="biography" rows="3"><?= htmlspecialchars($editData['biography']) ?></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="contract" value="1" <?= $editData['contract_agreed']?'checked':'' ?>> Я ознакомлен с контрактом</label></div>
                <button type="submit" class="btn-save">💾 Сохранить изменения</button>
                <a href="admin.php" style="margin-left:10px;">Отмена</a>
            </form>
        </div>
        <?php endif; ?>
        
        <h2>📋 Список всех заявок</h2>
        <?php if (empty($applications)): ?>
            <p>Нет данных</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Языки</th><th>Контракт</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach($applications as $app): ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= htmlspecialchars($app['full_name']) ?></td>
                        <td><?= htmlspecialchars($app['phone']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?php foreach($app['langs'] as $l): ?><span class="lang-badge"><?= htmlspecialchars($l) ?></span><?php endforeach; ?></td>
                        <td><?= $app['contract_agreed'] ? '✅ Да' : '❌ Нет' ?></td>
                        <td>
                            <a href="?edit=<?= $app['id'] ?>" class="btn-edit">✏️ Ред.</a>
                            <a href="?delete=<?= $app['id'] ?>" class="btn-delete" onclick="return confirm('Удалить заявку #<?= $app['id'] ?>?')">🗑️ Удалить</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2>📊 Статистика по языкам программирования</h2>
        <?php 
        // Защита от деления на ноль
        $max = 1;
        if (!empty($stats)) {
            $max = max(array_column($stats, 'cnt'));
            if ($max == 0) $max = 1;
        }
        
        foreach($stats as $s): 
            $pct = ($s['cnt'] / $max) * 100;
        ?>
            <div>
                <strong><?= htmlspecialchars($s['name']) ?></strong> (выбрали <?= $s['cnt'] ?> пользователей)
                <div class="stat-bar">
                    <div class="stat-fill" style="width: <?= $pct ?>%"><?= $s['cnt'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <hr>
        <a href="index.php" class="back-link">← Вернуться к форме регистрации</a>
        <a href="admin_logout.php" class="back-link logout">Выйти из админ-панели</a>
    </div>
</body>
</html>
