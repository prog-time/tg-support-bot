<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>

    <style>
        body {
            height: 2000px;
        }
    </style>

    <link rel="stylesheet" href="/live_chat/css/style.css">
</head>
<body>


<script>
    (function () {
        const startMessage = "Добрый день. Какой у вас вопрос?";
        const appUrl = "https://tg-support-bot.ru";
        const chatToken = "1QK5iFaEIjIpvptaTCBut6XTEdrjIkwtZobU7Smdi4gGPFXMmv55V1FlYiIs";

        function generateUniqueKey() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            const bytes = crypto.getRandomValues(new Uint8Array(12));
            let result = '';
            for (let i = 0; i < 12; i++) {
                result += chars[bytes[i] % chars.length];
            }
            return result;
        }

        let loadMessagesInterval;

        const widgetId = 'prog-time-widget';
        let lastMessageId = localStorage.getItem('pt_chat_last_message_id') || 0;

        let externalId = localStorage.getItem('pt_chat_external_id');
        if (!externalId) {
            externalId = generateUniqueKey();
            localStorage.setItem('pt_chat_external_id', externalId);
        }

        const apiUrl = `${appUrl}/api/external/${externalId}`;

        if (document.getElementById(widgetId)) {
            return;
        }

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

        const container = document.createElement('div');
        container.id = widgetId;
        container.setAttribute('role', 'region');
        container.setAttribute('aria-label', 'Онлайн-чат поддержки');

        container.style.display = 'none';

        container.innerHTML = `
        <header class="ptw_header">
            <div class="ptw_block_logo">
                <img src="../live_chat/image/manager.png" class="ptw_logo">
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

                <div class="ptw_file_field_block">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M13.3643 8.53806L7.14889 14.7535C6.56765 15.3347 6.56765 16.2771 7.14889 16.8584V16.8584C7.73014 17.4396 8.67252 17.4396 9.25376 16.8584L18.0481 8.06397C19.1894 6.92272 19.1899 5.07254 18.0493 3.93065V3.93065C16.9078 2.78785 15.0558 2.78732 13.9137 3.92949L4.93981 12.9033C3.23957 14.6036 3.23957 17.3602 4.93981 19.0604V19.0604C6.64004 20.7607 9.39668 20.7607 11.0969 19.0604L17.7556 12.4018" stroke="#999EA1" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <input id="ptw_file_field" class="ptw_file_field" type="file" aria-label="Прикрепить файл">
                </div>
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

        function addDateLine(date) {
            const dateLine = `
            <div class="date-line">
                <hr class="line">
                <div class="date-text">Вчера</div>
            </div>
            `;
        }

        function sendTextMessage() {
            const input = container.querySelector('#ptw_text_field');
            const message = input.value.trim();
            if (!message) {
                return;
            }

            if (loadMessagesInterval) {
                clearInterval(loadMessagesInterval);
            }

            fetch(apiUrl + `/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + chatToken // если нужно
                },
                body: JSON.stringify({
                    text: input.value,
                })
            }).then(response => {
                if (!response.ok) {
                    throw new Error(`Ошибка: ${response.status}`);
                }
                return response.json();
            }).then(data => {
                if (data.status) {
                    input.value = '';
                    container.querySelector('.ptw_send_but').disabled = true;

                    createMessageBlock(data.result, 'client');

                    localStorage.setItem('pt_chat_last_message_id', data.result.message_id);
                    loadMessagesInterval = setInterval(loadMessages, 3000);
                }
            }).catch(error => {
                console.error("Ошибка при отправке:", error);
            });
        }

        function createMessageBlock(messageData) {
            const messagesContainer = container.querySelector('#ptw_messages');
            const messageEl = document.createElement('section');

            let typeMessage = messageData.message_type === 'incoming' ? 'client' : 'manager';

            if (typeMessage === 'manager') {
                messageEl.className = 'ptw_message_block ptw_manager_message_block';
            } else {
                messageEl.className = 'ptw_message_block ptw_client_message_block';
            }

            let messageContent = "";
            if (messageData.content_type === 'document') {
                messageContent += `<div><img class="imageMessage" src="${messageData.file_url}" /></div>`;
            } else {
                messageContent += `<div>${messageData.text}</div>`;
            }

            const timeSend = (new Date(messageData.date.replace(/(\d{2})\.(\d{2})\.(\d{4})/, "$3-$2-$1"))).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            messageContent += `<div class="ptw_message_date">${timeSend}</div>`;

            messageEl.innerHTML = messageContent;

            messagesContainer.appendChild(messageEl);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function getStartMessage() {
            let dataMessage = localStorage.getItem('pt_chat_start_message');

            try {
                dataMessage = JSON.parse(dataMessage);
            } catch (e) {
                dataMessage = null;
            }

            if (!dataMessage) {
                dataMessage = {
                    "message_id": 1755203954,
                    "message_type": "outgoing",
                    "date": (new Date()).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    }).replace(/,/, ''),
                    "content_type": "text",
                    "text": startMessage
                };

                localStorage.setItem('pt_chat_start_message', JSON.stringify(dataMessage));
            }

            return dataMessage;
        }

        function sendStartMessage() {
            if (loadMessagesInterval) {
                clearInterval(loadMessagesInterval);
            }

            createMessageBlock(getStartMessage());
        }

        async function loadMessages(getAll = false) {
            try {
                const messagesContainer = document.getElementById('ptw_messages');

                if (getAll) {
                    sendStartMessage();
                }

                let url = apiUrl + `/messages`;
                if (!getAll) {
                    url += `?after_id=${lastMessageId}`;
                }

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + chatToken
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ошибка: ${response.status} ${response.statusText}`);
                }

                const text = await response.text();
                if (!text) {
                    throw new Error('Пустой ответ от сервера');
                }

                let responseData;
                try {
                    responseData = JSON.parse(text);
                } catch (e) {
                    throw new Error(`Ошибка парсинга JSON: ${e.message}`);
                }

                if (!responseData.messages) {
                    throw new Error('Ключ messages не найден!');
                } else if (!Array.isArray(responseData.messages)) {
                    throw new Error('Ответ не является массивом сообщений!');
                } else if (responseData.messages.length === 0) {
                    return;
                }

                responseData.messages.forEach(msg => {
                    let addStatus = false;

                    if (getAll) {
                        addStatus = true;
                    } else if (!getAll && msg.message_type === 'outgoing') {
                        addStatus = true;
                    }

                    if (addStatus) {

                        // if (lastMessageId) {
                        //     localStorage.setItem('pt_chat_last_message_id', lastMessageId);
                        // }

                        createMessageBlock(msg);

                        if (lastMessageId < msg.message_id) {
                            lastMessageId = msg.message_id
                        }
                    }
                });

                if (lastMessageId) {
                    localStorage.setItem('pt_chat_last_message_id', lastMessageId);
                }

                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Ошибка загрузки сообщений:', error);

                if (loadMessagesInterval) {
                    clearInterval(loadMessagesInterval);
                }
            }
        }

        butWidget.addEventListener('click', () => {
            butWidget.style.display = 'none'
            container.style.display = 'flex';

            loadMessagesInterval = setInterval(loadMessages, 3000);
        });

        container.querySelector('.ptw_close_but').addEventListener('click', () => {
            container.style.display = 'none';

            document.querySelector('.ptw-butWidget').style.display = 'block'

            if (loadMessagesInterval) {
                clearInterval(loadMessagesInterval);
            }
        });

        container.querySelector('.ptw_file_field_block').addEventListener('click', () => {
            container.querySelector('.ptw_file_field').click();
        });

        container.querySelector('.ptw_send_but').addEventListener('click', sendTextMessage);

        container.querySelector('.ptw_text_field').addEventListener('input', (e) => {
            let value = e.target.value;
            if (value.length > 0) {
                container.querySelector('.ptw_send_but').disabled = false;
            } else {
                container.querySelector('.ptw_send_but').disabled = true;
            }
        });

        container.querySelector('#ptw_text_field').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') sendTextMessage();
        });

        container.querySelector('#ptw_file_field').addEventListener('change', async (e) => {
            e.preventDefault();

            const fileInput = document.querySelector('input[type="file"]');
            const file = fileInput.files[0];

            if (!file) {
                alert('Файл не выбран!');
                return;
            }

            if (file.size > 60 * 1024 * 1024) {
                alert('Файл слишком большой! Максимум 60 МБ.');
                return;
            }

            if (loadMessagesInterval) {
                clearInterval(loadMessagesInterval);
            }

            const formData = new FormData();
            formData.append('uploaded_file', file);

            try {
                const response = await fetch(apiUrl + '/files', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${chatToken}`
                    },
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Ошибка: ${response.status} ${response.statusText}`);
                }

                const text = await response.text();
                if (!text) {
                    throw new Error('Пустой ответ от сервера');
                }

                let responseData;
                try {
                    responseData = JSON.parse(text);
                    if (!responseData || typeof responseData !== 'object') {
                        throw new Error('Ответ не является валидным JSON-объектом');
                    }
                } catch (e) {
                    throw new Error(`Ошибка парсинга JSON: ${e.message}`);
                }

                if (responseData.status) {
                    e.target.value = ''; // Очистка поля ввода файла
                    createMessageBlock(responseData.result, 'client');
                    localStorage.setItem('pt_chat_last_message_id', responseData.result.message_id);
                    loadMessagesInterval = setInterval(loadMessages, 3000);
                }
            } catch (error) {
                console.error('Ошибка при отправке файла:', error);
                loadMessagesInterval = setInterval(loadMessages, 3000); // Возобновляем интервал
            }
        });

        loadMessages(true);

    })();

</script>


<style>
    /*.chat-box {*/
    /*    position: fixed;*/
    /*    bottom: 20px;*/
    /*    right: 20px;*/
    /*    width: 300px;*/
    /*    height: 400px;*/
    /*    background: white;*/
    /*    border: 1px solid #ccc;*/
    /*    border-radius: 10px;*/
    /*    display: flex;*/
    /*    flex-direction: column;*/
    /*    font-family: sans-serif;*/
    /*    box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);*/
    /*    z-index: 9999;*/
    /*}*/

    /*.chat-box .messages {*/
    /*    flex: 1;*/
    /*    overflow-y: auto;*/
    /*    padding: 10px;*/
    /*}*/

    /*.chat-box .input {*/
    /*    display: flex;*/
    /*    border-top: 1px solid #eee;*/
    /*}*/

    /*.chat-box .input input {*/
    /*    flex: 1;*/
    /*    border: none;*/
    /*    padding: 10px;*/
    /*    font-size: 14px;*/
    /*}*/

    /*.chat-box .input button {*/
    /*    border: none;*/
    /*    padding: 10px 15px;*/
    /*    background: #007bff;*/
    /*    color: white;*/
    /*    cursor: pointer;*/
    /*}*/

    /*.chat-box .input button:hover {*/
    /*    background: #0056b3;*/
    /*}*/
</style>

</body>
</html>
