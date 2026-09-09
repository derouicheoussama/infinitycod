FROM node:24-alpine
WORKDIR /app
COPY package.json ./
COPY src ./src
COPY server.js ./
RUN mkdir -p storage/keys storage/releases storage/logs storage/backups
EXPOSE 8787
ENV PORT=8787
CMD ["node", "server.js"]
