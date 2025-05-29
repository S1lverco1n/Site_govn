<?php
require_once "functions.php";

if (isLoggedIn()) {
    redirect("index.php");
}

$username_input = $email_input = $password_input = $confirm_password_input = "";
$register_error = $register_success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = trim($_POST["username"]);
    $email_input = trim($_POST["email"]);
    $password_input_val = trim($_POST["password"]);
    $confirm_password_input_val = trim($_POST["confirm_password"]);

    if (empty($username_input) || empty($email_input) || empty($password_input_val) || empty($confirm_password_input_val)) {
        $register_error = "Пожалуйста, заполните все поля.";
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Неверный формат email.";
    } elseif (strlen($password_input_val) < 6) {
        $register_error = "Пароль должен быть не менее 6 символов.";
    } elseif ($password_input_val !== $confirm_password_input_val) {
        $register_error = "Пароли не совпадают.";
    } else {
        try {
            $sql_check = "SELECT id FROM users WHERE username = :username OR email = :email";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->bindParam(':username', $username_input, PDO::PARAM_STR);
            $stmt_check->bindParam(':email', $email_input, PDO::PARAM_STR);
            $stmt_check->execute();

            if ($stmt_check->fetchColumn()) {
                $register_error = "Пользователь с таким именем или email уже существует.";
            } else {
                $hashed_password = password_hash($password_input_val, PASSWORD_DEFAULT);
                // При регистрации роль всегда 'user' по умолчанию (задано в CREATE TABLE)
                $sql_insert = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->bindParam(':username', $username_input, PDO::PARAM_STR);
                $stmt_insert->bindParam(':email', $email_input, PDO::PARAM_STR);
                $stmt_insert->bindParam(':password', $hashed_password, PDO::PARAM_STR);

                if ($stmt_insert->execute()) {
                    $register_success = "Регистрация прошла успешно! Теперь вы можете <a href='login.php'>войти</a>.";
                    $_POST = array();
                    $username_input = $email_input = "";
                } else {
                    $register_error = "Что-то пошло не так при регистрации. Пожалуйста, попробуйте еще раз.";
                }
            }
        } catch (PDOException $e) {
            $register_error = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Регистрация нового пользователя</h1>
        <nav>
             <a href="index.php">Главная</a> |
             <a href="login.php">Вход</a>
        </nav>
    </header>
    <main class="container">
        <section>
             <h2>Создание аккаунта</h2>
            <?php if (!empty($register_error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($register_error); ?></p>
            <?php endif; ?>
            <?php if (!empty($register_success)): ?>
                <p class="success-message"><?php echo $register_success; // htmlspecialchars уже применен к части ссылки ?></p>
            <?php endif; ?>

            <?php if (empty($register_success)): ?>
            <form action="register.php" method="post">
                <div>
                    <label for="username">Имя пользователя:</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username_input); ?>" required>
                </div>
                <div>
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email_input); ?>" required>
                </div>
                <div>
                    <label for="password">Пароль (мин. 6 символов):</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div>
                    <label for="confirm_password">Подтвердите пароль:</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit">Зарегистрироваться</button>
            </form>
            <?php endif; ?>
            <p style="margin-top: 20px;">Уже есть аккаунт? <a href="login.php">Войти</a></p>
        </section>
    </main>
    <footer>
        <div class="container">
            <p>© <?php echo date("D"); ?> Твоя компание</p>
        </div>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>