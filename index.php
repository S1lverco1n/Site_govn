<?php
require_once "functions.php"; // Подключаем функции, $pdo и сессии

// ---- AJAX ОБРАБОТЧИКИ ----
// Эта часть кода выполняется, только если пришел POST-запрос с параметром 'action'
if (isset($_POST['action'])) {
    header('Content-Type: application/json'); // Всегда отправляем JSON в ответ на AJAX
    $response = ['success' => false, 'message' => 'Неизвестное действие или не выполнен вход.'];

    if (!isLoggedIn()) { // Проверка авторизации для всех AJAX действий
        echo json_encode($response);
        exit;
    }

    $current_user_id = $_SESSION['id'];

    // --- Загрузка данных поста для редактирования ---
    if ($_POST['action'] === 'get_post_data' && isset($_POST['post_id'])) {
        $post_id = filter_var($_POST['post_id'], FILTER_VALIDATE_INT);
        if ($post_id) {
            try {
                $sql = "SELECT p.id, p.user_id, p.content, p.image_path FROM posts p WHERE p.id = :post_id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                $stmt->execute();
                $post = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($post) {
                    if (isAdmin() || $post['user_id'] == $current_user_id) { // Проверка прав
                        $response = ['success' => true, 'post' => $post];
                    } else {
                        $response['message'] = 'У вас нет прав для просмотра данных этого поста.';
                    }
                } else {
                    $response['message'] = 'Пост для редактирования не найден.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Ошибка базы данных при получении данных поста: ' . $e->getMessage();
            }
        } else {
            $response['message'] = 'Некорректный ID поста для редактирования.';
        }
        echo json_encode($response);
        exit; // Завершаем выполнение скрипта после AJAX ответа
    }

    // --- Обновление поста ---
    if ($_POST['action'] === 'update_post' && isset($_POST['post_id'])) {
        $post_id = filter_var($_POST['post_id'], FILTER_VALIDATE_INT);
        $new_content = isset($_POST['post_content']) ? trim($_POST['post_content']) : '';
        // Чекбокс delete_current_image отправляет 'true' как строку, если отмечен
        $delete_current_image = isset($_POST['delete_current_image']) && $_POST['delete_current_image'] === 'true';


        if (!$post_id || empty($new_content)) {
            $response['message'] = 'ID поста и текст не могут быть пустыми для обновления.';
            echo json_encode($response);
            exit;
        }

        try {
            // Получаем текущие данные поста, чтобы проверить владельца и старый путь к изображению
            $sql_get_current = "SELECT user_id, image_path FROM posts WHERE id = :post_id";
            $stmt_get_current = $pdo->prepare($sql_get_current);
            $stmt_get_current->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt_get_current->execute();
            $current_post_data = $stmt_get_current->fetch(PDO::FETCH_ASSOC);

            if (!$current_post_data) {
                $response['message'] = 'Пост для обновления не найден в базе данных.';
                echo json_encode($response);
                exit;
            }

            // Проверка прав на редактирование
            if (!isAdmin() && $current_post_data['user_id'] != $current_user_id) {
                $response['message'] = 'У вас нет прав для редактирования этого поста.';
                echo json_encode($response);
                exit;
            }

            $new_image_path_relative = $current_post_data['image_path']; // По умолчанию старое изображение

            // 1. Удаление текущего изображения, если отмечен чекбокс
            if ($delete_current_image && !empty($new_image_path_relative)) {
                deleteFile(UPLOAD_DIR . $new_image_path_relative);
                $new_image_path_relative = null; // Очищаем путь в БД
            }

            // 2. Обработка загрузки нового изображения (если есть)
            if (isset($_FILES["post_image_edit"]) && $_FILES["post_image_edit"]["error"] == UPLOAD_ERR_OK) {
                $image = $_FILES["post_image_edit"];
                $image_ext = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
                $allowed_extensions = ["jpg", "jpeg", "png", "gif"];

                if (in_array($image_ext, $allowed_extensions) && $image["size"] < 5000000) { // 5MB
                    // Если было старое изображение и оно НЕ было удалено чекбоксом, удаляем его перед загрузкой нового
                    if (!empty($current_post_data['image_path']) && $current_post_data['image_path'] == $new_image_path_relative) { // т.е. если чекбокс не был нажат
                        deleteFile(UPLOAD_DIR . $current_post_data['image_path']);
                    }
                    
                    $new_file_name = uniqid('postimg_', true) . "." . $image_ext; // Добавил префикс
                    if (move_uploaded_file($image["tmp_name"], UPLOAD_DIR . $new_file_name)) {
                        $new_image_path_relative = $new_file_name;
                    } else {
                         $response['message'] = 'Ошибка при перемещении загруженного файла (редактирование).';
                         echo json_encode($response); exit;
                    }
                } else {
                    $response['message'] = 'Недопустимый тип или размер нового изображения (редактирование).';
                    echo json_encode($response); exit;
                }
            } elseif (isset($_FILES["post_image_edit"]) && $_FILES["post_image_edit"]["error"] != UPLOAD_ERR_NO_FILE) {
                 // Если файл был выбран, но произошла другая ошибка при загрузке
                 $response['message'] = 'Ошибка при загрузке нового изображения (редактирование): код ' . $_FILES["post_image_edit"]["error"];
                 echo json_encode($response); exit;
            }
            // Если ошибок нет, обновляем пост в БД
            $sql_update = "UPDATE posts SET content = :content, image_path = :image_path, updated_at = strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')
                           WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':content', $new_content, PDO::PARAM_STR);
            $stmt_update->bindParam(':image_path', $new_image_path_relative, PDO::PARAM_STR); // Может быть null
            $stmt_update->bindParam(':id', $post_id, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                // Получаем обновленные данные поста для отправки на клиент (для JS обновления)
                $sql_get_updated = "SELECT p.id, p.user_id, p.content, p.image_path, p.created_at, p.updated_at, u.username
                                    FROM posts p JOIN users u ON p.user_id = u.id
                                    WHERE p.id = :post_id";
                $stmt_get_updated = $pdo->prepare($sql_get_updated);
                $stmt_get_updated->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                $stmt_get_updated->execute();
                $updated_post_data = $stmt_get_updated->fetch(PDO::FETCH_ASSOC);

                $response = ['success' => true, 'message' => 'Пост успешно обновлен!', 'updated_post' => $updated_post_data];
            } else {
                $response['message'] = 'Не удалось обновить пост в базе данных.';
            }

        } catch (PDOException $e) {
            $response['message'] = 'Ошибка базы данных при обновлении поста: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit; // Завершаем выполнение скрипта после AJAX ответа
    }

    // Если 'action' не подошел ни под одно из условий
    echo json_encode($response);
    exit;
}
// ---- КОНЕЦ AJAX ОБРАБОТЧИКОВ ----


// ---- ОБЫЧНАЯ ЛОГИКА СТРАНИЦЫ (НЕ AJAX) ----
$flash_message = null;
if (isset($_SESSION['flash_message'])) { // Для сообщений из других страниц (если были бы)
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$post_content_input = ""; // Для формы добавления поста
$post_error = "";         // Ошибки добавления
$post_success = "";       // Успех добавления
$delete_error = "";       // Ошибки удаления
$delete_success = "";     // Успех удаления

// Обработка добавления нового поста (через обычную отправку формы)
if (isLoggedIn() && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_post'])) {
    $post_content_input = trim($_POST["post_content"]);
    $user_id = $_SESSION["id"];
    $image_path_relative = null;

    if (empty($post_content_input)) {
        $post_error = "Пожалуйста, введите текст поста.";
    } else {
        // Обработка загрузки файла для нового поста
        if (isset($_FILES["post_image"]) && $_FILES["post_image"]["error"] == UPLOAD_ERR_OK) {
            $image = $_FILES["post_image"];
            $image_ext = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
            $allowed_extensions = ["jpg", "jpeg", "png", "gif"];

            if (in_array($image_ext, $allowed_extensions) && $image["size"] < 5000000) { // 5MB
                $new_image_name = uniqid('postimg_', true) . "." . $image_ext;
                if (move_uploaded_file($image["tmp_name"], UPLOAD_DIR . $new_image_name)) {
                    $image_path_relative = $new_image_name;
                } else {
                    $post_error .= " Ошибка при перемещении загруженного файла.";
                }
            } else {
                $post_error .= " Недопустимый тип или размер файла (макс. 5MB).";
            }
        } elseif (isset($_FILES["post_image"]) && $_FILES["post_image"]["error"] != UPLOAD_ERR_NO_FILE) {
            $post_error .= " Ошибка при загрузке файла: код " . $_FILES["post_image"]["error"];
        }

        // Добавляем пост, только если не было ошибок (включая ошибки загрузки файла)
        if (empty($post_error)) {
            try {
                $sql_insert_post = "INSERT INTO posts (user_id, content, image_path) VALUES (:user_id, :content, :image_path)";
                $stmt_insert_post = $pdo->prepare($sql_insert_post);
                $stmt_insert_post->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_insert_post->bindParam(':content', $post_content_input, PDO::PARAM_STR);
                $stmt_insert_post->bindParam(':image_path', $image_path_relative, PDO::PARAM_STR); // Может быть NULL

                if ($stmt_insert_post->execute()) {
                    // Вместо прямого вывода $post_success, делаем редирект для чистоты (предотвращает повторную отправку)
                    header("Location: index.php?post_added=1");
                    exit;
                } else {
                    $post_error = "Не удалось добавить пост в базу данных.";
                }
            } catch (PDOException $e) {
                $post_error = "Ошибка базы данных при добавлении поста: " . $e->getMessage();
            }
        }
    }
}
// Показ сообщения после редиректа
if (isset($_GET['post_added']) && $_GET['post_added'] == 1) {
    $post_success = "Пост успешно добавлен!";
}


// Обработка удаления поста (через обычную отправку формы)
if (isLoggedIn() && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_post'])) {
    $post_id_to_delete = $_POST['post_id'];
    $current_user_id = $_SESSION['id'];

    try {
        // Сначала получим данные поста (включая user_id и image_path)
        $sql_get_post_data = "SELECT user_id, image_path FROM posts WHERE id = :post_id";
        $stmt_get_post_data = $pdo->prepare($sql_get_post_data);
        $stmt_get_post_data->bindParam(':post_id', $post_id_to_delete, PDO::PARAM_INT);
        $stmt_get_post_data->execute();
        $post_data_for_delete = $stmt_get_post_data->fetch();

        if (!$post_data_for_delete) {
             $delete_error = "Пост для удаления не найден.";
        } else {
            $can_delete = false;
            if (isAdmin() || ($post_data_for_delete['user_id'] == $current_user_id) ) {
                $can_delete = true;
            }

            if ($can_delete) {
                $sql_delete_post = "DELETE FROM posts WHERE id = :post_id";
                $stmt_delete_post = $pdo->prepare($sql_delete_post);
                $stmt_delete_post->bindParam(':post_id', $post_id_to_delete, PDO::PARAM_INT);

                if ($stmt_delete_post->execute() && $stmt_delete_post->rowCount() > 0) {
                    // Удаляем файл изображения, если он был
                    if (!empty($post_data_for_delete['image_path'])) {
                        deleteFile(UPLOAD_DIR . $post_data_for_delete['image_path']);
                    }
                    header("Location: index.php?post_deleted=1"); // Редирект после успеха
                    exit;
                } else {
                    $delete_error = "Не удалось удалить пост (возможно, он уже удален).";
                }
            } else {
                $delete_error = "У вас нет прав для удаления этого поста.";
            }
        }
    } catch (PDOException $e) {
        $delete_error = "Ошибка базы данных при удалении поста: " . $e->getMessage();
    }
}
// Показ сообщения после редиректа
if (isset($_GET['post_deleted']) && $_GET['post_deleted'] == 1) {
    $delete_success = "Пост успешно удален!";
}
if (isset($_GET['update_success']) && $_GET['update_success'] == 1 && isset($_GET['post_id'])) { // Сообщение после AJAX редактирования, если JS делает редирект
    $post_success = "Пост #" . htmlspecialchars($_GET['post_id']) . " успешно обновлен!";
}


// Получение всех постов для отображения
$posts = [];
try {
    $sql_select_posts = "
        SELECT p.id, p.user_id, p.content, p.image_path, p.created_at, p.updated_at, u.username
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ";
    $posts = $pdo->query($sql_select_posts)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $post_error = "Ошибка при загрузке постов: " . $e->getMessage(); // Отобразим как ошибку добавления
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лента Новостей</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Лента Новостей</h1>
        <nav>
            <?php if (isAdmin()): ?>
                <span>Привет, <strong style="color: #fff;"><?php echo htmlspecialchars($_SESSION["username"]); ?> (Админ)</strong>!</span> |
            <?php elseif (isLoggedIn()): ?>
                <span>Привет, <strong style="color: #fff;"><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>!</span> |
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
                 <a href="logout.php">Выйти</a>
            <?php else: ?>
                 <a href="login.php">Вход</a> | <a href="register.php">Регистрация</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <?php // Отображение всех типов сообщений ?>
        <?php if ($flash_message): /* Для сообщений из других сессий, если были бы */ ?>
            <p class="<?php echo ($flash_message['type'] == 'success' ? 'success' : 'error'); ?>-message"><?php echo htmlspecialchars($flash_message['text']); ?></p>
        <?php endif; ?>
        <?php if (!empty($post_success)): ?><p class="success-message"><?php echo htmlspecialchars($post_success); ?></p><?php endif; ?>
        <?php if (!empty($post_error)): ?><p class="error-message"><?php echo htmlspecialchars($post_error); ?></p><?php endif; ?>
        <?php if (!empty($delete_success)): ?><p class="success-message"><?php echo htmlspecialchars($delete_success); ?></p><?php endif; ?>
        <?php if (!empty($delete_error)): ?><p class="error-message"><?php echo htmlspecialchars($delete_error); ?></p><?php endif; ?>

        <?php if (isLoggedIn()): ?>
            <section class="add-post-form section-styled">
                <h2>Добавить новый пост</h2>
                <form action="index.php" method="post" enctype="multipart/form-data">
                    <div>
                        <label for="post_content">Ваш текст:</label>
                        <textarea name="post_content" id="post_content" rows="4" required><?php echo htmlspecialchars($post_content_input); ?></textarea>
                    </div>
                    <div>
                        <label for="post_image">Изображение (макс. 5MB, jpg/png/gif):</label>
                        <input type="file" name="post_image" id="post_image" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <button type="submit" name="add_post">Опубликовать</button>
                </form>
            </section>
        <?php elseif(!empty($posts)): // Показать призыв к регистрации/входу, если посты есть, но пользователь не залогинен ?>
             <section class="landing-content section-styled">
                <p>Чтобы добавлять посты и видеть больше, пожалуйста, <a href="login.php">войдите</a> или <a href="register.php">зарегистрируйтесь</a>.</p>
            </section>
        <?php endif; ?>

        <section class="posts-list">
            <h2>Последние посты</h2>
            <?php if (empty($posts) && empty($post_error) /* Не показывать "нет постов", если была ошибка загрузки */): ?>
                <p>Пока нет ни одного поста. <?php if(isLoggedIn()): ?>Станьте первым!<?php else: ?> <a href="login.php">Войдите</a>, чтобы добавить пост.<?php endif; ?></p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-item" data-postitemid="<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <strong class="post-author"><?php echo htmlspecialchars($post['username']); ?></strong>
                            <span class="post-date">
                                Опубл.: <?php echo date('d.m.y H:i', strtotime($post['created_at'])); ?>
                                <?php if ($post['updated_at']): ?>
                                    (изм.: <?php echo date('d.m.y H:i', strtotime($post['updated_at'])); ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($post['image_path']): ?>
                            <div class="post-image-container">
                                <img src="images/<?php echo htmlspecialchars($post['image_path']); ?>" alt="Изображение поста" class="post-image">
                            </div>
                        <?php endif; ?>
                        <div class="post-content">
                            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        </div>
                        
                        <?php if (isLoggedIn() && (isAdmin() || $_SESSION['id'] == $post['user_id'])): ?>
                        <div class="post-actions">
                            <a href="#" class="edit-post-btn js-open-edit-modal button-like-link" data-postid="<?php echo $post['id']; ?>">Редактировать</a>
                            <form action="index.php" method="post" onsubmit="return confirm('Вы уверены, что хотите удалить этот пост?');" style="display: inline;">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <button type="submit" name="delete_post" class="delete-post-btn button-like-link">Удалить</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- Модальное окно редактирования -->
    <div id="customEditModalOverlay" class="cem-overlay" style="display: none;">
        <div class="cem-wing cem-left-wing" id="cemLeftWing"></div>
        <div class="cem-wing cem-right-wing" id="cemRightWing"></div>
        <div class="cem-editor-pane" id="cemEditorPane">
            <div class="cem-title-bar">
                <span class="cem-title-text">РЕДАКТИРОВАНИЕ</span>
            </div>
            <div class="cem-form-container">
                <form id="customEditPostForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_post"> <!-- Для AJAX-обработчика -->
                    <input type="hidden" name="post_id" id="cemEditPostId">
                    <div>
                        <label for="cemEditPostContent">Текст поста:</label>
                        <textarea name="post_content" id="cemEditPostContent" rows="5" required></textarea>
                    </div>
                    
                    <div id="cemCurrentImagePreviewContainer" style="margin-bottom: 10px; display:none;">
                        <p><strong>Текущее изображение поста:</strong></p>
                        <img id="cemCurrentEditImage" src="" alt="Текущее изображение" class="cem-preview-image">
                        <label class="cem-checkbox-label">
                            <input type="checkbox" name="delete_current_image" id="cemDeleteCurrentImageCheckbox" value="true"> 
                            Удалить текущее изображение поста
                        </label>
                    </div>

                    <div>
                        <label for="cemEditPostImage">Заменить/добавить изображение для поста (макс. 5MB):</label>
                        <input type="file" name="post_image_edit" id="cemEditPostImage" accept="image/jpeg,image/png,image/gif">
                    </div>
                    
                    <div class="cem-modal-actions">
                        <button type="submit" class="cem-button cem-button-save">Сохранить</button>
                        <button type="button" id="cemCloseEditModal" class="cem-button cem-button-cancel">Отмена</button>
                    </div>
                    <div id="cemEditFormMessage" class="cem-form-message"></div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>© <?php echo date("D"); ?> Твоя компание</p>
        </div>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>