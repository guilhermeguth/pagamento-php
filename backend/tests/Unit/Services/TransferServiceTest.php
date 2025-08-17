<?php

namespace Tests\Unit\Services;

use App\Services\TransferService;
use PHPUnit\Framework\TestCase;

class TransferServiceTest extends TestCase
{
    public function testTransferServiceExists()
    {
        $this->assertTrue(class_exists(TransferService::class));
    }
}