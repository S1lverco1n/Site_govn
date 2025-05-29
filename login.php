<?php
require_once "functions.php";

if (isLoggedIn()) {
    redirect("index.php");
}

$login_identifier_input = $password_input_val = "";
$login_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier_input = trim($_POST["login_identifier"]);
    $password_input_val = trim($_POST["password"]);

    if (empty($login_identifier_input) || empty($password_input_val)) {
        $login_error = "Пожалуйста, введите email/имя пользователя и пароль.";
    } else {
        try {
            // Выбираем также 'role'
            $sql = "SELECT id, username, email, password, role FROM users WHERE username = :login_id OR email = :login_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':login_id', $login_identifier_input, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password_input_val, $user['password'])) {
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $user['id'];
                    $_SESSION["username"] = $user['username'];
                    $_SESSION["email"] = $user['email'];
                    $_SESSION["role"] = $user['role']; // Сохраняем роль
                    redirect("index.php");
                } else {
                    $login_error = "Неверный пароль.";
                }
            } else {
                $login_error = "Аккаунт с таким email/именем пользователя не найден.";
            }
        } catch (PDOException $e) {
            $login_error = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Вход в систему</h1>
        <nav>
             <a href="index.php">Главная</a> |
             <a href="register.php">Регистрация</a>
        </nav>
    </header>
    <main class="container">
        <section>
            <h2>Авторизация</h2>
            <?php if(!empty($login_error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($login_error); ?></p>
            <?php endif; ?>
            <form action="login.php" method="post">
                <div>
                    <label for="login_identifier">Email или Имя пользователя:</label>
                    <input type="text" name="login_identifier" id="login_identifier" value="<?php echo htmlspecialchars($login_identifier_input); ?>" required>
                </div>
                <div>
                    <label for="password">Пароль:</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit">Войти</button>
            </form>
            <p style="margin-top: 20px;">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
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