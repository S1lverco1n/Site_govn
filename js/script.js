document.addEventListener('DOMContentLoaded', function() {
    // ---- Логика смены темы (розовой) ----
    const themeBodyElement = document.body;
    const themeMainContainer = document.querySelector('main.container');

    function togglePinkTheme() {
        if (themeBodyElement) themeBodyElement.classList.toggle('pink-theme');
    }

    if (themeBodyElement && themeMainContainer) {
        themeBodyElement.addEventListener('click', function(event) {
            if (event.target.closest('.cem-overlay')) return;
            const clickedInsideMain = themeMainContainer.contains(event.target);
            if (!clickedInsideMain && event.target !== themeMainContainer) {
                togglePinkTheme();
            }
        });
    }
    // ---- Конец логики смены темы ----
   
    // ---- Логика НОВОГО модального окна редактирования (cem) ----
    const cemModalOverlay = document.getElementById('customEditModalOverlay');
    const cemEditorPane = document.getElementById('cemEditorPane');
    const cemEditPostForm = document.getElementById('customEditPostForm');
    const cemEditPostIdInput = document.getElementById('cemEditPostId');
    const cemEditPostContentInput = document.getElementById('cemEditPostContent');
    const cemCurrentImagePreviewContainer = document.getElementById('cemCurrentImagePreviewContainer');
    const cemCurrentEditImage = document.getElementById('cemCurrentEditImage');
    const cemDeleteCurrentImageCheckbox = document.getElementById('cemDeleteCurrentImageCheckbox');
    const cemEditPostImageInput = document.getElementById('cemEditPostImage');
    const cemCloseEditModalButton = document.getElementById('cemCloseEditModal');
    const cemEditFormMessageDiv = document.getElementById('cemEditFormMessage');
    const cemModalTitleBar = document.querySelector('.cem-title-bar');
    const cemModalTitleText = document.querySelector('.cem-title-text');
    
    const cemModalLeftPanel = document.getElementById('cemLeftWing');
    const cemModalRightPanel = document.getElementById('cemRightWing');

    // +++ Аудио элемент +++
    let editSound = null; 
    const soundPath = 'js/spring.mp3'; // Убедитесь, что путь правильный

    // Инициализация звука (лучше при первом взаимодействии пользователя)
    function initSound() {
        if (!editSound) {
            try {
                editSound = new Audio(soundPath);
                editSound.preload = 'auto'; 
            } catch (e) {
                console.error("Не удалось создать аудио объект:", e);
            }
        }
    }
    // Вызовем initSound при первом клике на любую кнопку "Редактировать"
    // или можно вызвать его сразу, если политики браузера позволяют предзагрузку.
    // Для большей надежности - при клике.

    // ПУТИ К ВАШИМ ИЗОБРАЖЕНИЯМ ДЛЯ БОКОВЫХ ПАНЕЛЕЙ
    const cemLeftPanelImage = ''; // Пример: 'images/left-wing.jpg'; 
    const cemRightPanelImage = '';// Пример: 'images/right-wing.jpg';

    function checkImageExists(url, callback) {
        if (!url) { callback(false); return; }
        const img = new Image();
        img.onload = () => callback(true);
        img.onerror = () => callback(false);
        img.src = url;
    }
    
    if (cemModalLeftPanel) {
        checkImageExists(cemLeftPanelImage, (exists) => { 
            if (exists) cemModalLeftPanel.style.backgroundImage = `url('${cemLeftPanelImage}')`; 
        });
    }
    if (cemModalRightPanel) {
         checkImageExists(cemRightPanelImage, (exists) => { 
            if (exists) cemModalRightPanel.style.backgroundImage = `url('${cemRightPanelImage}')`; 
        });
    }

    document.querySelectorAll('.js-open-edit-modal').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Инициализируем звук, если еще не сделано
            if (!editSound) {
                initSound();
            }

            // Воспроизведение звука
            if (editSound) {
                editSound.currentTime = 0; 
                const playPromise = editSound.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => {
                        console.warn("Не удалось воспроизвести звук при клике на редактирование:", error);
                    });
                }
            }

            const postId = this.dataset.postid;
            if (postId) loadPostDataForCemEdit(postId);
            else console.error('Post ID не найден.');
        });
    });

    if (cemCloseEditModalButton) cemCloseEditModalButton.addEventListener('click', closeCemModal);
    if (cemModalOverlay) {
        cemModalOverlay.addEventListener('click', function(event) {
            if (event.target === cemModalOverlay) closeCemModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && cemModalOverlay && cemModalOverlay.classList.contains('active')) {
                closeCemModal();
            }
        });
    }
    
    function adjustCemTitleTextSpacing() {
        if (cemModalTitleBar && cemModalTitleText) {
            cemModalTitleText.style.letterSpacing = 'normal'; 
            cemModalTitleText.style.transform = 'scaleX(1)';
            requestAnimationFrame(() => {
                const barWidth = cemModalTitleBar.clientWidth;
                const textEl = cemModalTitleText;
                const originalText = textEl.textContent;
                const initialTextWidth = textEl.offsetWidth;
                const targetTextWidth = barWidth * 0.98; 

                if (originalText.length <= 1) { textEl.style.letterSpacing = '0'; return; }

                if (initialTextWidth < targetTextWidth) {
                    const requiredAdditionalSpace = targetTextWidth - initialTextWidth;
                    let additionalSpacingPerGap = requiredAdditionalSpace / (originalText.length - 1);
                    additionalSpacingPerGap = Math.max(0, additionalSpacingPerGap);
                    const currentLetterSpacing = parseFloat(getComputedStyle(textEl).letterSpacing.replace('px','')) || 0;
                    textEl.style.letterSpacing = `${Math.min(currentLetterSpacing + additionalSpacingPerGap, 20)}px`;
                } else if (initialTextWidth > barWidth) {
                    textEl.style.transform = `scaleX(${(barWidth / initialTextWidth) * 0.98})`;
                    textEl.style.transformOrigin = 'center';
                }
            });
        }
    }

    function positionAndSizeWings() {
        if (!cemEditorPane || !cemModalLeftPanel || !cemModalRightPanel || !cemModalOverlay) return;
        const editorRect = cemEditorPane.getBoundingClientRect();
        const overlayRect = cemModalOverlay.getBoundingClientRect();

        if (editorRect.height < 50 || editorRect.width < 50) { // Если редактор еще не виден или слишком мал
             // Установить какие-то дефолтные размеры, чтобы анимация сработала
            const defaultWingSize = Math.min(window.innerHeight * 0.3, window.innerWidth * 0.2, 200);
            [cemModalLeftPanel, cemModalRightPanel].forEach(wing => {
                wing.style.width = `${defaultWingSize}px`;
                wing.style.height = `${defaultWingSize}px`;
                wing.style.top = `calc(50% - ${defaultWingSize / 2}px)`; // Центр оверлея
            });
            cemModalLeftPanel.style.left = `calc(50% - ${cemEditorPane.offsetWidth/2 + defaultWingSize + 20}px)`;
            cemModalRightPanel.style.left = `calc(50% + ${cemEditorPane.offsetWidth/2 + 20}px)`;
            return; // Размеры редактора еще не окончательные, выйдем
        }

        const wingDimension = Math.max(150, Math.min(editorRect.height * 0.65, 250));

        [cemModalLeftPanel, cemModalRightPanel].forEach(wing => {
            wing.style.width = `${wingDimension}px`;
            wing.style.height = `${wingDimension}px`;
            // Вертикальное позиционирование крыла: его центр на уровне 2/3 высоты редактора
            const wingTop = (editorRect.top - overlayRect.top) + (editorRect.height * 0.60) - (wingDimension / 2);
            wing.style.top = `${wingTop}px`;
        });
        const gap = 20; // Отступ между редактором и крылом
        cemModalLeftPanel.style.left = `${editorRect.left - overlayRect.left - wingDimension - gap}px`;
        cemModalRightPanel.style.left = `${editorRect.right - overlayRect.left + gap}px`;
    }


    function openCemModal() {
        if (cemModalOverlay) {
            cemModalOverlay.style.display = 'flex';
            requestAnimationFrame(() => {
                positionAndSizeWings(); // Устанавливаем размеры и позиции крыльев
                setTimeout(() => {
                     cemModalOverlay.classList.add('active'); // Запускаем CSS анимации
                     adjustCemTitleTextSpacing(); 
                }, 20); 
            });
            if (themeBodyElement) themeBodyElement.style.overflow = 'hidden';
        }
    }

    function closeCemModal() {
        if (cemModalOverlay) {
            cemModalOverlay.classList.remove('active');
            setTimeout(() => {
                if(cemModalOverlay) cemModalOverlay.style.display = 'none';
                resetCemEditForm();
                // Сброс инлайновых стилей крыльев, чтобы при следующем открытии они пересчитались
                if(cemModalLeftPanel) cemModalLeftPanel.style.cssText = ''; 
                if(cemModalRightPanel) cemModalRightPanel.style.cssText = '';
            }, 500); // Время = самой долгой анимации
            if (themeBodyElement) themeBodyElement.style.overflow = 'auto';
        }
    }

    function resetCemEditForm() {
        if (cemEditPostForm) cemEditPostForm.reset();
        if (cemCurrentImagePreviewContainer) cemCurrentImagePreviewContainer.style.display = 'none';
        if (cemCurrentEditImage) cemCurrentEditImage.src = '';
        if (cemEditFormMessageDiv) {
            cemEditFormMessageDiv.textContent = '';
            cemEditFormMessageDiv.className = 'cem-form-message';
        }
    }

    function loadPostDataForCemEdit(postId) {
        if (!cemEditPostForm || !cemEditPostIdInput || !cemEditPostContentInput || !cemCurrentImagePreviewContainer || !cemCurrentEditImage || !cemDeleteCurrentImageCheckbox || !cemEditPostImageInput) {
            console.error("CEM JS: Один или несколько элементов формы редактирования не найдены."); return;
        }
        resetCemEditForm(); 
        const formData = new FormData();
        formData.append('action', 'get_post_data');
        formData.append('post_id', postId);

        fetch('index.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success && data.post) {
                cemEditPostIdInput.value = data.post.id;
                cemEditPostContentInput.value = data.post.content;
                if (data.post.image_path) {
                    cemCurrentEditImage.src = 'images/' + data.post.image_path;
                    cemCurrentImagePreviewContainer.style.display = 'block';
                } else {
                    cemCurrentImagePreviewContainer.style.display = 'none';
                }
                cemDeleteCurrentImageCheckbox.checked = false;
                openCemModal(); // Открываем модалку ПОСЛЕ загрузки данных
            } else { alert('Ошибка получения данных поста: ' + (data.message || 'Неизвестная ошибка.')); }
        })
        .catch(error => { console.error('Fetch error (loadPostDataForCemEdit):', error); alert('Сетевая ошибка при загрузке данных для редактирования.'); });
    }

    if (cemEditPostForm) {
        cemEditPostForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (cemEditFormMessageDiv) { cemEditFormMessageDiv.textContent = 'Сохранение...'; cemEditFormMessageDiv.className = 'cem-form-message'; }
            const formData = new FormData(this);

            fetch('index.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (cemEditFormMessageDiv) {
                    cemEditFormMessageDiv.textContent = data.message || (data.success ? 'Успешно сохранено!' : 'Неизвестный ответ.');
                    cemEditFormMessageDiv.className = `cem-form-message ${data.success ? 'success' : 'error'}`;
                }
                if (data.success) {
                    if (data.updated_post) updatePostOnPage(data.updated_post);
                    setTimeout(closeCemModal, 1500);
                }
            })
            .catch(error => {
                console.error('Fetch error (submitCemEditForm):', error);
                 if (cemEditFormMessageDiv) { cemEditFormMessageDiv.textContent = 'Сетевая ошибка при сохранении.'; cemEditFormMessageDiv.className = 'cem-form-message error';}
            });
        });
    }
    
    function updatePostOnPage(updatedPostData) {
        if (!updatedPostData || typeof updatedPostData.id === 'undefined') { console.error('CEM JS: Некорректные данные для обновления поста.', updatedPostData); return; }
        const postArticle = document.querySelector(`.post-item[data-postitemid="${updatedPostData.id}"]`);
        if (!postArticle) { return; } 

        const contentElement = postArticle.querySelector('.post-content p');
        let imageContainer = postArticle.querySelector('.post-image-container');
        const postDateElement = postArticle.querySelector('.post-date');

        if (contentElement) contentElement.innerHTML = nl2br(escapeHtml(updatedPostData.content || ''));
        
        if (updatedPostData.image_path) {
            if (!imageContainer) {
                imageContainer = document.createElement('div'); imageContainer.className = 'post-image-container';
                const postContentDiv = postArticle.querySelector('.post-content');
                if (postContentDiv) postArticle.insertBefore(imageContainer, postContentDiv);
                else postArticle.appendChild(imageContainer);
            }
            let imgElement = imageContainer.querySelector('.post-image');
            if (!imgElement) {
                imgElement = document.createElement('img'); imgElement.className = 'post-image'; imgElement.alt = 'Изображение поста';
                imageContainer.innerHTML = ''; 
                imageContainer.appendChild(imgElement);
            }
            imgElement.src = 'images/' + updatedPostData.image_path;
            imageContainer.style.display = 'block';
        } else if (imageContainer) {
            imageContainer.innerHTML = ''; imageContainer.style.display = 'none';
        }

        if (postDateElement) {
            let dateHtml = `Опубл.: ${formatDate(updatedPostData.created_at || '')}`;
            if (updatedPostData.updated_at) dateHtml += ` (изм.: ${formatDate(updatedPostData.updated_at)})`;
            postDateElement.innerHTML = dateHtml;
        }
    }
    function escapeHtml(unsafe) { if (typeof unsafe !== 'string') return ''; return unsafe.replace(/&/g, "&").replace(/</g, "<").replace(/>/g, ">").replace(/"/g, ",").replace(/'/g, "'"); }
    function nl2br (str) { if (typeof str !== 'string') return ''; return str.replace(/(\r\n|\n\r|\r|\n)/g, "<br />"); }
    function formatDate(dateStringSQL) {
        if (!dateStringSQL) return ''; 
        try { 
            const date = new Date(dateStringSQL.replace(' ', 'T')); 
            if (isNaN(date.getTime())) return dateStringSQL; 
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = String(date.getFullYear()).slice(-2);
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}.${month}.${year} ${hours}:${minutes}`;
        } catch (e) { return dateStringSQL; }
    }
});