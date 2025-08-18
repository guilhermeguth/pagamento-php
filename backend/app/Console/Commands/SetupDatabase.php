<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;

class SetupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:database {--force : Força recriar o banco}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configura o banco de dados inicial do sistema de pagamento';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Configurando banco de dados...');

        try {
            $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
            
            if ($schemaManager->tablesExist(['users']) && !$this->option('force')) {
                $this->info('Banco de dados já configurado.');
                return 0;
            }

            if ($this->option('force')) {
                $this->warn('Forçando recriação do banco...');
                $this->call('doctrine:schema:drop', ['--force' => true]);
            }

            $this->call('doctrine:schema:create');
            $this->info('Schema criado com sucesso!');

            $this->createAdminUser();

            $this->info('Banco de dados configurado com sucesso!');
            $this->info('Login: admin@sistema.com | Senha: admin123');

            return 0;

        } catch (\Exception $e) {
            $this->error('Erro ao configurar banco: ' . $e->getMessage());
            return 1;
        }
    }

    private function createAdminUser(): void
    {
        try {
            $userRepository = $this->entityManager->getRepository(User::class);
            $existingUser = $userRepository->findOneBy(['email' => 'admin@sistema.com']);

            if ($existingUser) {
                $this->info('Usuário admin já existe.');
                return;
            }

            $user = new User();
            $user->setName('Admin Sistema');
            $user->setDocument('00000000000');
            $user->setEmail('admin@sistema.com');
            $user->setPassword(password_hash('admin123', PASSWORD_DEFAULT));
            $user->setType(User::TYPE_COMMON);
            $user->setBalance(1000.00);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->info('Usuário admin criado com sucesso!');

        } catch (\Exception $e) {
            $this->warn('Erro ao criar usuário admin: ' . $e->getMessage());
        }
    }
}
