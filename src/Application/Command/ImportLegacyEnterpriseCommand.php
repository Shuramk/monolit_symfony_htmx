<?php declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: "legacy:import-enterprise", description: "Imports the legacy enterprise profile into monolit_app.")]
final class ImportLegacyEnterpriseCommand extends Command {
    public function __construct(
        #[Autowire("@doctrine.dbal.legacy_connection")]
        private readonly Connection $legacy,
        #[Autowire("@doctrine.dbal.app_connection")]
        private readonly Connection $app,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $row = $this->legacy->fetchAssociative(
            "SELECT eID, offNameVAT, offNamePE, NameEnt, NameEntShort, EDRPOU_VAT, EDRPOU_PE, paymentAccount_VAT, paymentAccount_PE, address, www, email, logo, phone, accounting_phone, wText FROM sklad_enterprise WHERE eID = 1",
        );

        if ($row === false) {
            $io->error("Legacy enterprise profile not found.");

            return Command::FAILURE;
        }

        $id = (int) $row["eID"];
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");

        $this->app->transactional(function () use ($row, $id, $now): void {
            $this->app->executeStatement(
                "INSERT INTO enterprise_profiles (id, legal_name_vat, legal_name_pe, display_name, short_name, edrpou_vat, edrpou_pe, payment_account_vat, payment_account_pe, address, website, email, logo_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE legal_name_vat = VALUES(legal_name_vat), legal_name_pe = VALUES(legal_name_pe), display_name = VALUES(display_name), short_name = VALUES(short_name), edrpou_vat = VALUES(edrpou_vat), edrpou_pe = VALUES(edrpou_pe), payment_account_vat = VALUES(payment_account_vat), payment_account_pe = VALUES(payment_account_pe), address = VALUES(address), website = VALUES(website), email = VALUES(email), logo_path = VALUES(logo_path), updated_at = VALUES(updated_at)",
                [$id, $row["offNameVAT"], $row["offNamePE"], $row["NameEnt"], $row["NameEntShort"], $row["EDRPOU_VAT"], $row["EDRPOU_PE"], $row["paymentAccount_VAT"], $row["paymentAccount_PE"], $row["address"], $row["www"], $row["email"], $row["logo"], $now, $now],
            );
            $this->app->executeStatement(
                "INSERT INTO enterprise_warranty_texts (enterprise_profile_id, source_html, created_at, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE source_html = VALUES(source_html), updated_at = VALUES(updated_at)",
                [$id, $row["wText"] ?? "", $now, $now],
            );
            $this->app->executeStatement("DELETE FROM enterprise_phones WHERE enterprise_profile_id = ?", [$id]);

            foreach ($this->phones($row["phone"], "sales") as $phone) {
                $this->app->insert("enterprise_phones", $phone + ["enterprise_profile_id" => $id]);
            }

            foreach ($this->phones($row["accounting_phone"], "accounting") as $phone) {
                $this->app->insert("enterprise_phones", $phone + ["enterprise_profile_id" => $id]);
            }
        });

        $io->success("Enterprise profile imported.");

        return Command::SUCCESS;
    }

    /** @return list<array{kind: string, position: int, number: string}> */
    private function phones(mixed $json, string $kind): array {
        $numbers = json_decode((string) $json, true);

        if (!is_array($numbers)) {
            return [];
        }

        $phones = [];

        foreach ($numbers as $position => $number) {
            if (!is_string($number) || $number === "") {
                continue;
            }

            $phones[] = ["kind" => $kind, "position" => (int) $position, "number" => $number];
        }

        return $phones;
    }
}
