(function () {
    const socketUrl = import.meta.env.VITE_APP_URL;

    // Получаем токен из query string
    function getTokenFromWidgetScript() {
        const scripts = document.getElementsByTagName('script');
        let myScript = null;

        // Ищем скрипт, который содержит 'widget.js' в src
        for (let i = 0; i < scripts.length; i++) {
            if (scripts[i].src && /widget\.js/.test(scripts[i].src)) {
                myScript = scripts[i];
                break;
            }
        }

        if (!myScript) {
            console.error("Widget script не найден!");
            return null;
        }

        let src = myScript.src;

        // Если src относительный, добавляем origin
        if (!/^https?:\/\//.test(src)) {
            src = window.location.origin + src;
        }

        const url = new URL(src);
        return url.searchParams.get('token');
    }

    const chatToken = getTokenFromWidgetScript();

    if (!chatToken) {
        console.error("Токен не указан. Виджет не будет работать.");
        return; // прерываем выполнение скрипта
    }

    // Генерация уникального ключа пользователя
    function generateUniqueKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const bytes = crypto.getRandomValues(new Uint8Array(12));
        let result = '';
        for (let i = 0; i < 12; i++) {
            result += chars[bytes[i] % chars.length];
        }
        return result;
    }

    const widgetId = 'prog-time-widget';
    let externalId = localStorage.getItem('pt_chat_external_id');
    if (!externalId) {
        externalId = generateUniqueKey();
        localStorage.setItem('pt_chat_external_id', externalId);
    }

    if (document.getElementById(widgetId)) return;

    // Создаём кнопку виджета
    const butWidget = document.createElement('div');
    butWidget.setAttribute('class', 'ptw-butWidget');
    butWidget.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60" fill="none">
            <rect width="60" height="60" rx="30" fill="#0BA5EC"/>
            <path d="M24.6665 30H24.6785M29.9878 30H29.9998M35.3212 30H35.3332" stroke="white" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M29.9998 43.3334C37.3638 43.3334 43.3332 37.364 43.3332 30C43.3332 22.636 37.3638 16.6667 29.9998 16.6667C22.6358 16.6667 16.6665 22.636 16.6665 30C16.6665 32.1334 17.1678 34.1494 18.0572 35.9374C18.2945 36.412 18.3732 36.9547 18.2358 37.468L17.4425 40.436C17.3639 40.7297 17.364 41.039 17.4427 41.3327C17.5214 41.6263 17.676 41.8942 17.8909 42.1092C18.1059 42.3243 18.3736 42.479 18.6672 42.5579C18.9608 42.6368 19.2701 42.6371 19.5638 42.5587L22.5318 41.764C23.047 41.6339 23.592 41.6969 24.0638 41.9414C25.9078 42.8594 27.94 43.336 29.9998 43.3334Z" stroke="white" stroke-width="2"/>
        </svg>
    `;
    document.body.appendChild(butWidget);

    // Создаём контейнер чата
    const container = document.createElement('div');
    container.id = widgetId;
    container.setAttribute('role', 'region');
    container.setAttribute('aria-label', 'Онлайн-чат поддержки');
    container.style.display = 'none';

    container.innerHTML = `
        <header class="ptw_header">
            <div class="ptw_block_logo">
                <img src="`+ socketUrl +`/live_chat/image/manager.png" class="ptw_logo">
            </div>
            <div class="ptw_text">
                <div class="ptw_dop_heading">Напишите нам, мы онлайн!</div>
                <div class="ptw_name_manager">Илья</div>
            </div>
            <button class="ptw_close_but" type="button" aria-label="Закрыть чат">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7.91992 12H15.9199" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </header>

        <main class="ptw_content_block" id="ptw_messages"></main>

        <footer class="ptw_footer">
            <div class="ptw_form_block">
                <input id="ptw_text_field" class="ptw_text_field" placeholder="Введите сообщение" type="text" aria-label="Поле ввода сообщения">
<!--                <div class="ptw_file_field_block">-->
<!--                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">-->
<!--                        <path d="M13.3643 8.53806L7.14889 14.7535C6.56765 15.3347 6.56765 16.2771 7.14889 16.8584V16.8584C7.73014 17.4396 8.67252 17.4396 9.25376 16.8584L18.0481 8.06397C19.1894 6.92272 19.1899 5.07254 18.0493 3.93065V3.93065C16.9078 2.78785 15.0558 2.78732 13.9137 3.92949L4.93981 12.9033C3.23957 14.6036 3.23957 17.3602 4.93981 19.0604V19.0604C6.64004 20.7607 9.39668 20.7607 11.0969 19.0604L17.7556 12.4018" stroke="#999EA1" stroke-width="1.5" stroke-linecap="round"/>-->
<!--                    </svg>-->
<!--                    <input id="ptw_file_field" class="ptw_file_field" type="file" aria-label="Прикрепить файл">-->
<!--                </div>-->
            </div>

            <div class="ptw_send_but_block">
                <button disabled class="ptw_send_but" type="submit" aria-label="Отправить сообщение">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <rect width="48" height="48" rx="8" fill="#0BA5EC"/>
                        <path d="M21.9252 17.5251L29.0586 21.0918C32.2586 22.6918 32.2586 25.3084 29.0586 26.9084L21.9252 30.4751C17.1252 32.8751 15.1669 30.9084 17.5669 26.1168L18.2919 24.6751C18.4752 24.3084 18.4752 23.7001 18.2919 23.3334L17.5669 21.8834C15.1669 17.0918 17.1336 15.1251 21.9252 17.5251Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </footer>
    `;
    document.body.appendChild(container);

    // Подключаем Socket.IO
    const socket = io(socketUrl, {
        query: { externalId, token: chatToken }
    });

    // Функция создания сообщения в DOM
    function createMessageBlock(messageData) {
        const messagesContainer = container.querySelector('#ptw_messages');
        const messageEl = document.createElement('section');

        messageEl.className = messageData.message_type === 'incoming'
            ? 'ptw_message_block ptw_client_message_block'
            : 'ptw_message_block ptw_manager_message_block';

        const messageId = messageData.message_type === 'incoming' ? messageData.to_id : messageData.from_id
        messageEl.id = 'messageBlock_' + messageId

        let content = `<div class="contentBlock">`

        if (messageData.content_type === 'file') {
            content += `<img class="imageMessage" src="${messageData.file_url}" />`
        }

        if (messageData.text) {
            content += `<span>${messageData.text}</span>`
        }

        content += `</div>`

        const timeSend = new Date(messageData.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        content += `<div class="ptw_message_date">${timeSend}</div>`;
        messageEl.innerHTML = content;

        messagesContainer.appendChild(messageEl);
    }

    function editMessageBlock(messageData) {
        const messageId = messageData.message_type === 'incoming' ? messageData.to_id : messageData.from_id
        let messageNameId = "messageBlock_" + messageId
        let messageBlock = document.getElementById(messageNameId)

        if (messageBlock) {
            messageBlock.querySelector('.contentBlock').textContent = messageData.text
        }
    }

    // Отправка сообщения
    function sendTextMessage() {
        const input = container.querySelector('#ptw_text_field');
        const text = input.value.trim();
        if (!text) return;

        socket.emit('send_message', { text });
        input.value = '';
        container.querySelector('.ptw_send_but').disabled = true;
    }

    socket.on("connect", () => {
        // Запрос истории сообщений
        socket.emit("get_history");
    });

    // Получение сообщений от сервера
    socket.on("history_messages", (messagesList) => {
        for (let item in messagesList) {
            createMessageBlock(messagesList[item]);
        }
    });

    // Получение ответа от Laravel API
    socket.on("receive_message", (messageData) => {
        createMessageBlock(messageData);
    });

    // Получение ответа от Laravel API
    socket.on("edit_message", (messageData) => {
        editMessageBlock(messageData);
    });

    // Обработчики UI
    butWidget.addEventListener('click', () => {
        butWidget.style.display = 'none';
        container.style.display = 'flex';

        setTimeout(() => {
            const messagesContainer = container.querySelector('#ptw_messages');
            messagesContainer.scrollTo({
                top: messagesContainer.scrollHeight,
                behavior: 'smooth'
            });
        }, 50);
    });

    container.querySelector('.ptw_close_but').addEventListener('click', () => {
        container.style.display = 'none';
        butWidget.style.display = 'block';
    });

    container.querySelector('.ptw_send_but').addEventListener('click', sendTextMessage);
    container.querySelector('#ptw_text_field').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') sendTextMessage();
    });

    container.querySelector('.ptw_text_field').addEventListener('input', (e) => {
        let value = e.target.value;
        if (value.length > 0) {
            container.querySelector('.ptw_send_but').disabled = false;
        } else {
            container.querySelector('.ptw_send_but').disabled = true;
        }
    });

})();
