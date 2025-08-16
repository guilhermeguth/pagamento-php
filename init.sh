#!/bin/bash

echo "🚀 Iniciando Sistema de Pagamento..."

# Verifica se o Docker está rodando
if ! docker info > /dev/null 2>&1; then
    echo "Docker não está rodando. Inicie o Docker e tente novamente."
    exit 1
fi

echo "Subindo containers..."
docker-compose up -d

echo "Aguardando containers iniciarem..."
sleep 15

echo "Instalando dependências do backend..."
docker-compose exec php composer install --optimize-autoloader

echo "Configurando banco de dados..."
docker-compose exec php php artisan migrate --force

echo "Configurando cache e rotas..."
docker-compose exec php php artisan config:cache
docker-compose exec php php artisan route:cache

echo "Publicando configurações do Doctrine..."
docker-compose exec php php artisan vendor:publish --provider="LaravelDoctrine\ORM\DoctrineServiceProvider"

echo "Instalando dependências do frontend..."
docker-compose exec node npm install

echo "Sistema pronto!"
echo ""
echo "Acesse o frontend em: http://localhost:5173"
echo "API disponível em: http://localhost:80/api"
echo ""
echo "Para iniciar o frontend em modo desenvolvimento:"
echo "docker-compose exec node npm run dev"
