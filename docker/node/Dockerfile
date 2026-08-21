FROM node:20-alpine

WORKDIR /app

RUN apk add --no-cache git

EXPOSE 3000

ENV HOST=0.0.0.0
ENV PORT=3000

CMD ["sh", "-c", "if [ ! -d node_modules/nuxt ]; then echo '📦 Instalando dependencias de Nuxt en Linux...'; npm install --legacy-peer-deps; fi; npx nuxi dev --host 0.0.0.0 --port 3000"]
