FROM node:18

WORKDIR /usr/src/app

COPY package*.json ./
RUN npm install

COPY . .

# Запуск через nodemon для авто-перезагрузки
CMD ["npx", "nodemon", "--legacy-watch", "server.js"]
