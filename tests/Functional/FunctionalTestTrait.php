<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Functional;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Tmonier\SyliusAgenticFeedsPlugin\AgenticFeedsTestTrait;

trait FunctionalTestTrait
{
    use AgenticFeedsTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    private function loginAdmin(): AdminUserInterface
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');

        return $admin;
    }
}
