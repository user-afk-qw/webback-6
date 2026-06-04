<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета разработчика - Задание 5</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-content {
            padding: 30px;
        }

        /* Блок авторизации */
        .auth-links {
            text-align: right;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }

        .auth-links .logout-btn {
            color: #c33;
        }

        .user-greeting {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .required:after {
            content: " *";
            color: red;
        }

        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        /* Стиль для полей с ошибками */
        .error-field {
            border: 2px solid red !important;
            background-color: #fff0f0 !important;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-group label {
            display: inline-flex;
            align-items: center;
            font-weight: normal;
            margin-bottom: 0;
        }

        .radio-group input {
            width: auto;
            margin-right: 5px;
        }

        select[multiple] {
            height: 150px;
        }

        .checkbox-group {
            margin: 20px 0;
        }

        .checkbox-group label {
            display: inline-flex;
            align-items: center;
            font-weight: normal;
        }

        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        /* Сообщения об ошибках */
        .error-messages {
            background: #fee;
            border-left: 4px solid #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error-item {
            color: #c33;
            margin: 5px 0;
            font-size: 14px;
        }

        .success-message {
            background: #efe;
            color: #3c3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
        }

        .info-message {
            background: #e8f4fd;
            color: #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }

        .field-hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Анкета разработчика</h1>
            <p>Задание 5 - Авторизация и редактирование данных</p>
        </div>
        <div class="form-content">
            <!-- Блок авторизации -->
            <div class="auth-links">
                <?php if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                } ?>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <span class="user-greeting">👋 Привет, <strong><?= htmlspecialchars($_SESSION['login'] ?? '') ?></strong></span>
                    <a href="logout.php" class="logout-btn" onclick="return confirm('Выйти из системы?')">🚪 Выйти</a>
                <?php else: ?>
                    <a href="login.php">🔐 Войти для редактирования</a>
                <?php endif; ?>
            </div>

            <!-- Сообщения -->
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <?= $message ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Форма -->
            <form action="" method="POST">
                <!-- Поле ФИО -->
                <div class="form-group">
                    <label class="required">ФИО</label>
                    <input type="text" name="fio" 
                           id="fio"
                           class="<?= isset($errors['fio']) && $errors['fio'] ? 'error-field' : '' ?>"
                           value="<?= htmlspecialchars($values['fio'] ?? '') ?>"
                           placeholder="Иванов Иван Иванович">
                    <div class="field-hint">Только буквы, пробелы и дефисы. От 2 до 150 символов.</div>
                </div>
                
                <!-- Поле Телефон -->
                <div class="form-group">
                    <label class="required">Телефон</label>
                    <input type="tel" name="phone" 
                           id="phone"
                           class="<?= isset($errors['phone']) && $errors['phone'] ? 'error-field' : '' ?>"
                           value="<?= htmlspecialchars($values['phone'] ?? '') ?>"
                           placeholder="+7 (123) 456-78-90">
                    <div class="field-hint">Формат: +7 (123) 456-78-90 (10-20 символов)</div>
                </div>
                
                <!-- Поле Email -->
                <div class="form-group">
                    <label class="required">E-mail</label>
                    <input type="email" name="email" 
                           id="email"
                           class="<?= isset($errors['email']) && $errors['email'] ? 'error-field' : '' ?>"
                           value="<?= htmlspecialchars($values['email'] ?? '') ?>"
                           placeholder="ivan@example.com">
                    <div class="field-hint">Пример: user@domain.com</div>
                </div>
                
                <!-- Поле Дата рождения -->
                <div class="form-group">
                    <label class="required">Дата рождения</label>
                    <input type="date" name="birth_date" 
                           id="birth_date"
                           class="<?= isset($errors['birth_date']) && $errors['birth_date'] ? 'error-field' : '' ?>"
                           value="<?= htmlspecialchars($values['birth_date'] ?? '') ?>">
                    <div class="field-hint">Возраст должен быть от 18 до 120 лет</div>
                </div>
                
                <!-- Пол -->
                <div class="form-group">
                    <label class="required">Пол</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="gender" value="male" 
                                   id="gender_male"
                                   <?= (($values['gender'] ?? '') == 'male') ? 'checked' : '' ?>
                                   class="<?= isset($errors['gender']) && $errors['gender'] ? 'error-field' : '' ?>">
                            Мужской
                        </label>
                        <label>
                            <input type="radio" name="gender" value="female" 
                                   id="gender_female"
                                   <?= (($values['gender'] ?? '') == 'female') ? 'checked' : '' ?>
                                   class="<?= isset($errors['gender']) && $errors['gender'] ? 'error-field' : '' ?>">
                            Женский
                        </label>
                    </div>
                </div>
                
                <!-- Любимые языки программирования -->
                <div class="form-group">
                    <label class="required">Любимые языки программирования</label>
                    <select name="languages[]" multiple 
                            id="languages"
                            class="<?= isset($errors['languages']) && $errors['languages'] ? 'error-field' : '' ?>"
                            size="6">
                        <?php
                        $allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                        $selectedLangs = $values['languages'] ?? [];
                        if (!is_array($selectedLangs)) {
                            $selectedLangs = [];
                        }
                        foreach ($allLanguages as $lang):
                            $selected = in_array($lang, $selectedLangs) ? 'selected' : '';
                        ?>
                            <option value="<?= $lang ?>" <?= $selected ?>><?= $lang ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Удерживайте Ctrl (Cmd на Mac) для множественного выбора. Выберите хотя бы один язык.</div>
                </div>
                
                <!-- Биография -->
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" 
                              id="biography"
                              class="<?= isset($errors['biography']) && $errors['biography'] ? 'error-field' : '' ?>"
                              placeholder="Расскажите о себе..."><?= htmlspecialchars($values['biography'] ?? '') ?></textarea>
                    <div class="field-hint">Необязательно. Максимум 5000 символов.</div>
                </div>
                
                <!-- Чекбокс контракта -->
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="contract" value="1" 
                               id="contract"
                               <?= (($values['contract'] ?? '') == '1') ? 'checked' : '' ?>
                               class="<?= isset($errors['contract']) && $errors['contract'] ? 'error-field' : '' ?>">
                        Я ознакомлен(а) с контрактом и согласен(на) с условиями
                    </label>
                </div>
                
                <input type="submit" class="submit-btn" value="💾 Сохранить">
            </form>
        </div>
    </div>
</body>
</html>