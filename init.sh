#!/bin/bash

echo "Iniciando Sistema de Pagamento..."

# Verifica se o Docker está rodando
if ! docker info > /dev/null 2>&1; then
    echo "Docker não está rodando. Inicie o Docker e tente novamente."
    exit 1
fi

echo "Subindo containers..."
docker compose up -d

echo "Aguardando containers iniciarem..."
sleep 20

echo "Instalando dependências do backend..."
docker compose exec php composer install --optimize-autoloader

echo "Configurando banco de dados..."
docker compose exec php php artisan setup:database

echo "Limpando cache..."
docker compose exec php php artisan config:clear
docker compose exec php php artisan route:clear

echo "Instalando dependências do frontend..."
docker compose exec node npm install

echo "Compilando frontend..."
docker compose exec node npm run build

echo ""
echo "Sistema iniciado com sucesso!"
echo ""
echo "Informações importantes:"
echo "Backend API: http://localhost:8000/api"
echo "Frontend:    http://localhost:3000"
echo "Admin:       admin@sistema.com / admin123"
echo ""
echo "Para parar: docker compose down"
echo "Para logs:  docker compose logs -f"

echo "Instalando dependências do frontend..."
docker compose exec node npm install

echo "Sistema pronto!"
echo ""
echo "Acesse o frontend em: http://localhost:5173"
echo "API disponível em: http://localhost:80/api"
echo ""
echo "Para iniciar o frontend em modo desenvolvimento:"
echo "docker compose exec node npm run dev"
