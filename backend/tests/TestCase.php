<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

abstract class TestCase extends BaseTestCase
{
    protected bool $seeded = false;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar banco de teste apenas uma vez
        if (!$this->seeded) {
            $this->setupTestDatabase();
            $this->seeded = true;
        }
    }

    protected function setupTestDatabase(): void
    {
        // Criar schema das entidades Doctrine no banco de teste
        $entityManager = app(EntityManagerInterface::class);
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        // Recriar schema
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
