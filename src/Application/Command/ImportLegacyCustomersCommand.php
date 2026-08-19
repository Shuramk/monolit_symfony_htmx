<?php

declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: "legacy:import-customers", description: "Upserts the application Customer catalog from the read-only legacy database.")]
final class ImportLegacyCustomersCommand extends Command
{
    public function __construct(
        #[Autowire("@doctrine.dbal.legacy_connection")]
        private readonly Connection $legacy,
        #[Autowire("@doctrine.dbal.app_connection")]
        private readonly Connection $app,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customers = $this->legacy->fetchAllAssociative("SELECT customerID, customerName, customerPhone, customerPhone1, customerPhone2, registrationDate, person, contactPerson, buyer, stacker, cComment, address, email FROM sklad_customers ORDER BY customerID");
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");

        $this->app->transactional(function () use ($customers, $now): void {
            foreach ($customers as $customer) {
                $this->app->executeStatement(
                    "INSERT INTO customers (id, name, phone, phone_secondary, phone_tertiary, registered_at, person_type, contact_person, buyer_type, stacker_id, comment, address, email, imported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), phone = VALUES(phone), phone_secondary = VALUES(phone_secondary), phone_tertiary = VALUES(phone_tertiary), registered_at = VALUES(registered_at), person_type = VALUES(person_type), contact_person = VALUES(contact_person), buyer_type = VALUES(buyer_type), stacker_id = VALUES(stacker_id), comment = VALUES(comment), address = VALUES(address), email = VALUES(email), updated_at = VALUES(updated_at)",
                    [
                        (int) $customer["customerID"],
                        $customer["customerName"],
                        $this->phone($customer["customerPhone"]),
                        $this->phone($customer["customerPhone1"]),
                        $this->phone($customer["customerPhone2"]),
                        $customer["registrationDate"],
                        $customer["person"] ?: null,
                        $customer["contactPerson"] ?: null,
                        $customer["buyer"] ?: null,
                        $customer["stacker"] === null ? null : (int) $customer["stacker"],
                        $customer["cComment"] ?: null,
                        $customer["address"] ?: null,
                        $customer["email"] ?: null,
                        $now,
                        $now,
                    ],
                );
            }
        });

        (new SymfonyStyle($input, $output))->success(sprintf("Imported %d customers.", count($customers)));

        return Command::SUCCESS;
    }

    private function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return $phone === "" || $phone === "XXXXXXXXXX" ? null : $phone;
    }
}
