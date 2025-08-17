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

echo "Configurando ambiente..."
# Verificar se .env existe, se não criar a partir do exemplo
if [ ! -f "./backend/.env" ]; then
    echo "Criando arquivo .env..."
    if [ -f "./backend/.env.example" ]; then
        cp ./backend/.env.example ./backend/.env
    fi
fi

echo "Configurando diretórios e permissões..."
# Criar diretórios necessários
docker compose exec php mkdir -p /var/www/html/bootstrap/cache
docker compose exec php mkdir -p /var/www/html/storage/logs
docker compose exec php mkdir -p /var/www/html/storage/framework/cache
docker compose exec php mkdir -p /var/www/html/storage/framework/sessions
docker compose exec php mkdir -p /var/www/html/storage/framework/views

# Configurar permissões
docker compose exec php chown -R www-data:www-data /var/www/html/bootstrap/cache
docker compose exec php chown -R www-data:www-data /var/www/html/storage
docker compose exec php chmod -R 755 /var/www/html/bootstrap/cache
docker compose exec php chmod -R 755 /var/www/html/storage

echo "Configurando chaves da aplicação..."
# Gerar APP_KEY se não existir ou estiver vazia
if ! docker compose exec php grep -q "APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
    echo "Gerando nova chave da aplicação..."
    docker compose exec php php artisan key:generate
fi

echo "Configurando banco de dados..."
docker compose exec php php artisan setup:database

echo "Limpando cache..."
docker compose exec php php artisan config:clear
docker compose exec php php artisan route:clear
docker compose exec php php artisan cache:clear

echo "Verificando configuração da aplicação..."
# Testar conexão com o banco
docker compose exec php php artisan tinker --execute="echo 'Conexão DB: ' . (DB::connection()->getPdo() ? 'OK' : 'ERRO');"

# Verificar se as rotas da API estão funcionando
echo "Testando API..."
if curl -f -s http://localhost/api/health > /dev/null 2>&1; then
    echo "API: OK"
else
    echo "API: Aguardando inicialização..."
    sleep 5
fi

echo "Instalando dependências do frontend..."
docker compose exec node npm install

echo "Testando funcionalidades principais..."
# Testar rota de saúde da API
if curl -f -s http://localhost/api/health > /dev/null 2>&1; then
    echo "✓ API Health check: OK"
else
    echo "⚠ API Health check: Aguardando..."
    sleep 3
fi

echo ""
echo "Sistema iniciado com sucesso!"
echo ""
echo "Informações importantes:"
echo "Backend API: http://localhost:8000/api"
echo "Frontend:    http://localhost:5173"
echo "Admin:       admin@sistema.com / admin123"
echo ""
echo "Para parar: docker compose down"
echo "Para logs:  docker compose logs -f"
echo "Para dev:   docker compose exec node npm run dev"
