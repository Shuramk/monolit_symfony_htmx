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

#[AsCommand(name: "legacy:import-products", description: "Rebuilds the application Product catalog from the read-only legacy database.")]
final class ImportLegacyProductsCommand extends Command
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
        $products = $this->legacy->fetchAllAssociative("SELECT id, parent, name, name_uk, measure, storage1, storage2, storage3, price, sorting, type, pallets, invName, forms, weight_per_unit, minQuantity, enabled FROM sklad_products ORDER BY id");
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");

        $this->app->transactional(function () use ($products, $now): void {
            $this->app->executeStatement("DELETE FROM products");

            foreach ($products as $product) {
                $this->app->insert("products", [
                    "id" => (int) $product["id"],
                    "parent_id" => (int) $product["parent"] > 0 ? (int) $product["parent"] : null,
                    "name" => $product["name"],
                    "name_uk" => $product["name_uk"] ?: null,
                    "unit" => $product["measure"],
                    "storage_office" => $product["storage1"] ?? "0",
                    "storage_workshop" => $product["storage2"] ?? "0",
                    "storage_third" => $product["storage3"] ?? "0",
                    "price" => $product["price"],
                    "sorting" => (int) $product["sorting"],
                    "type" => (int) $product["type"],
                    "pallets" => $product["pallets"] ?: null,
                    "inventory_name" => $product["invName"] ?: null,
                    "forms" => $product["forms"] ?: null,
                    "weight_per_unit" => $product["weight_per_unit"] ?: null,
                    "min_quantity" => $product["minQuantity"] ?: null,
                    "enabled" => (int) $product["enabled"],
                    "imported_at" => $now,
                    "updated_at" => $now,
                ]);
            }
        });

        (new SymfonyStyle($input, $output))->success(sprintf("Imported %d products.", count($products)));

        return Command::SUCCESS;
    }
}
