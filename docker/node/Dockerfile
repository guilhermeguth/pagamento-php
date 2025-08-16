FROM node:18-alpine

RUN apk add --no-cache git

WORKDIR /app

EXPOSE 3000 5173

CMD ["sh", "-c", "npm install && npm run dev"]
