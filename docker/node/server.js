const express = require('express');
const { createServer } = require('node:http');
const { Server } = require('socket.io');

const domain = process.env.DOMAIN || 'https://tg-support-bot.ru';

const app = express();
app.use(express.json());

const server = createServer(app);
const io = new Server(server, {
    cors: {
        origin: "*",
    }
});

io.on('connection', (socket) => {
    const externalId = socket.handshake.query.externalId;
    const token = socket.handshake.query.token;

    function sendResponse(action, data) {
        for (let [id, socket] of io.sockets.sockets) {
            if (socket.handshake.query.externalId === externalId) {
                socket.emit(action, data);
            }
        }
    }

    socket.on("get_history", async () => {
        try {
            const url = `${domain}/api/external/${externalId}/messages`;

            const response = await fetch(url, {
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer " + token
                }
            });

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error("Ошибка! Вернулся не JSON:", text);
                return;
            }

            sendResponse("history_messages", data.messages)

        } catch (err) {
            console.error("Ошибка при получении истории сообщений:", err);
        }
    });

    socket.on("send_message", async (msg) => {
        try {
            const urlQuery = domain +`/api/external/`+ externalId +`/messages`

            const response = await fetch(urlQuery, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer " + token
                },
                body: JSON.stringify(msg)
            });

            const data = await response.json();

            sendResponse("receive_message", data.result)
        } catch (err) {
            console.error("Ошибка при отправке в Laravel API:", err);
        }

    });

    socket.on("disconnect");
});

// ---------------------- Push от Laravel ----------------------
app.post("/push-message", async (req, res) => {
    try {
        const { externalId, message, type_query } = req.body;

        if (!externalId || !message) {
            return res.status(400).json({ error: "externalId или message отсутствует" });
        }

        for (let [id, socket] of io.sockets.sockets) {
            if (socket.handshake.query.externalId === externalId) {
                switch (type_query) {
                    case 'send_message':
                        socket.emit("receive_message", message);
                        break

                    case 'edit_message':
                        socket.emit("edit_message", message);
                        break
                }
            }
        }

        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

server.listen(3000);
