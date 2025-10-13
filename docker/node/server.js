const express = require('express');
const { createServer } = require('node:http');
const { Server } = require('socket.io');

const domain = process.env.APP_URL || '';
const apiToken = process.env.API_TOKEN || '';

const allowedOrigins = process.env.ALLOWED_ORIGINS
    ? process.env.ALLOWED_ORIGINS.split(',')
    : [];

allowedOrigins.push(domain)

const app = express();
app.use(express.json());

const server = createServer(app);
const io = new Server(server, {
    cors: {
        origin: function (origin, callback) {
            if (!origin || allowedOrigins.includes(origin)) {
                callback(null, true);
            } else {
                callback(new Error("Not allowed by CORS"));
            }
        },
        methods: ["GET", "POST"]
    }
});

// Проверка подключения клиента по внешнему домену
io.use((socket, next) => {
    const origin = socket.handshake.headers.origin;
    if (!origin || allowedOrigins.includes(origin)) {
        next();
    } else {
        next(new Error('Connection from this origin is not allowed'));
    }
});

io.on('connection', (socket) => {
    const externalId = socket.handshake.query.externalId;

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
                    "Authorization": "Bearer " + apiToken
                }
            });
            const data = await response.json();
            sendResponse("history_messages", data.messages);
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
                    "Authorization": "Bearer " + apiToken
                },
                body: JSON.stringify(msg)
            });
            const data = await response.json();
            sendResponse("receive_message", data.result)
        } catch (err) {
            console.error("Ошибка при отправке в Laravel API:", err);
        }
    });

    socket.on("disconnect", () => {
        console.log("Клиент отключился:", socket.id);
    });
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

server.listen(3000, () => {
    console.log('server running at http://localhost:3000');
});
