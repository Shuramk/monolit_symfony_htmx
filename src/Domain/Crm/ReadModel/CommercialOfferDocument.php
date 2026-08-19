<?php declare(strict_types=1);

namespace App\Domain\Crm\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\ReadModel\LegacyProductText;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class CommercialOfferDocument {
    public function __construct(
        private Environment $twig,
        private LegacyProductText $productText,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function generate(array $deal, array $enterprise, array $phones, \DateTimeImmutable $now): array {
        $markup = (int) $deal['markup'];
        $lines = [];
        $total = Decimal::zero(2);
        foreach ($deal['lines'] as $line) {
            $price = Decimal::fromString($line['price'], 4);
            if ($markup > 0) {
                $price = $price->multiplyByInt(100 + $markup)->divideByInt(100);
            }
            $price = $price->toScale(2);
            $quantity = Decimal::fromString($line['quantity'], 3);
            $amount = $quantity->multiply($price)->toScale(2);
            $total = $total->add($amount);
            $lines[] = [
                'name' => $this->text((string) $line['product_name']),
                'unit' => $this->text((string) $line['unit']),
                'quantity' => $quantity->equals($quantity->toScale(2)) ? $quantity->format(2) : $quantity->format(3),
                'price' => $this->number($price),
                'amount' => $this->number($amount),
            ];
        }
        $headerPhones = [];
        foreach (['097', '095'] as $prefix) {
            foreach ($phones as $phone) {
                if (str_starts_with((string) $phone, $prefix)) {
                    $headerPhones[] = $phone;
                    break;
                }
            }
        }
        $address = str_replace('г.Николаев', 'м.Миколаїв', $this->text((string) $enterprise['address']));
        if (!str_starts_with($address, '54000')) {
            $address = '54000, Миколаївська обл., ' . $address;
        }
        $xml = $this->twig->render('crm/documents/offer.xml.twig', [
            'address' => $address,
            'phones' => array_slice($headerPhones ?: $phones, 0, 2),
            'customer' => $this->text((string) ($deal['customer_name'] ?? '')),
            'date' => $now->format('d.m.Y'),
            'lines' => $lines,
            'vat' => $markup === 20,
            'total' => $this->number($total),
        ]);
        $logo = file_get_contents($this->projectDir . '/assets/images/monolit-logo.png');
        if ($logo === false) {
            throw new \RuntimeException('Не удалось прочитать логотип коммерческого предложения.');
        }

        return [
            'xml' => $xml,
            'files' => [
                'word/media/monolit-logo.png' => $logo,
                'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    . '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                    . 'Target="media/monolit-logo.png"/></Relationships>',
            ],
        ];
    }

    private function text(string $value): string {

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', strip_tags($this->productText->format($value))) ?? '';
    }

    private function number(Decimal $value): string {

        return rtrim(rtrim($value->format(2), '0'), '.');
    }
}
