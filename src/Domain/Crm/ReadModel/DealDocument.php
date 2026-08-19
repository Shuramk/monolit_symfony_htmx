<?php declare(strict_types=1);

namespace App\Domain\Crm\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Enterprise\Repository\EnterpriseViewRepository;
use App\Domain\Order\ReadModel\LegacyProductText;

final readonly class DealDocument {
    public function __construct(
        private CrmRepository $deals,
        private EnterpriseViewRepository $enterprises,
        private LegacyProductText $productText,
        private CommercialOfferDocument $commercialOffer,
    ) {}

    public function generate(int $id, string $kind, \DateTimeImmutable $now): string {
        $deal = $this->deals->deal($id);
        if (!in_array($kind,['invoice','offer'],true)) {
            throw new \DomainException('Неизвестный документ.');
        }
        if (in_array($deal['state'],['cancelled','hidden'],true) || !$deal['lines']) {
            throw new \DomainException('Для документа нужна неотменённая сделка с продукцией.');
        }

        $markup = (int) $deal['markup'];
        if ($kind === 'invoice' && $markup === 0) {
            throw new \DomainException('Для счёта выберите НДС или ФОП.');
        }

        $enterprise = $this->enterprises->findDocumentProfile();
        if (!$enterprise) {
            throw new \DomainException('Заполните реквизиты предприятия.');
        }

        $vat = $markup === 20;
        $legalName = $enterprise[$vat ? 'legal_name_vat' : 'legal_name_pe'] ?: $enterprise['display_name'];
        $body = $this->paragraph((string) $legalName,true);
        $body .= $this->paragraph((string) $enterprise['address']);
        $phones = $enterprise['phones'];
        if ($kind === 'offer') {
            $offer = $this->commercialOffer->generate($deal, $enterprise, $phones, $now);

            return $this->archive($offer['xml'], $offer['files']);
        }

        $body .= $this->paragraph('Тел.: ' . implode('; ', $phones) . '  ' . ($enterprise['email'] ?? ''));
        if ($kind === 'invoice') {
            $body .= $this->paragraph('ЄДРПОУ / ІПН: ' . ($enterprise[$vat ? 'edrpou_vat' : 'edrpou_pe'] ?? ''));
            $body .= $this->paragraph('Р/р: ' . ($enterprise[$vat ? 'payment_account_vat' : 'payment_account_pe'] ?? ''));
        }
        $body .= $this->paragraph(($kind === 'invoice' ? 'Рахунок на оплату №__________' : 'Комерційна пропозиція') . ' від ' . $now->format('d.m.Y'),true);
        $body .= $this->paragraph('Одержувач: ' . ($deal['customer_name'] ?? '') . ($deal['phone'] ? ', тел. ' . $deal['phone'] : ''));
        if ($kind === 'offer') {
            $body .= $this->paragraph('На ваш запит наша компанія може запропонувати наступну продукцію:');
        }

        $headers = ['№', 'Назва', 'Од.', 'Кількість', $kind === 'offer' && $vat ? 'Ціна з ПДВ' : 'Ціна без ПДВ', $kind === 'offer' && $vat ? 'Сума з ПДВ' : 'Сума без ПДВ'];
        $table = $this->row($headers,true);
        $total = Decimal::zero(2);
        foreach ($deal['lines'] as $index=>$line) {
            $price = Decimal::fromString($line['price'], 4);
            if ($markup > 0 && ($kind === 'offer' || !$vat)) {
                $price = $price->multiplyByInt(100 + $markup)->divideByInt(100)->toScale(4);
            }
            if ($kind === 'offer') {
                $price = $price->toScale(2);
            }
            $quantity = Decimal::fromString($line['quantity'],3);
            $amount = $quantity->multiply($price)->toScale(2);
            $total = $total->add($amount);
            $name = $kind === 'invoice' ? $line['product_name_uk'] : $line['product_name'];
            $table .= $this->row([(string) ($index+1), $name, $line['unit'], $quantity->format(), $price->format(), $amount->format()]);
        }

        $vatAmount = $kind === 'invoice' && $vat ? $total->multiplyByInt(20)->divideByInt(100)->toScale(2) : Decimal::zero(2);
        $body .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="400"/><w:gridCol w:w="5000"/><w:gridCol w:w="650"/><w:gridCol w:w="1300"/><w:gridCol w:w="1300"/><w:gridCol w:w="1400"/></w:tblGrid>' . $table . '</w:tbl>';
        $body .= $this->paragraph(($kind === 'offer' && $vat ? 'Разом з ПДВ: ' : 'Разом без ПДВ: ') . $total->format() . ' грн',true);
        if ($kind === 'invoice') {
            $body .= $this->paragraph('ПДВ: ' . $vatAmount->format() . ' грн',true);
            $body .= $this->paragraph('Всього до сплати: ' . $total->add($vatAmount)->format() . ' грн',true);
            $body .= $this->paragraph('Виписав: ________________________________________');
            $body .= $this->paragraph('Рахунок дійсний до ' . $now->modify('+3 days')->format('d.m.Y'));
        } else {
            $body .= $this->paragraph('Транспортувальні піддони є зворотною тарою. При поверненні піддонів у належному вигляді заставна сума буде повернена.');
            $body .= $this->paragraph('Гарантія на продукцію 7 років.');
            $body .= $this->paragraph('Комерційна пропозиція дійсна протягом 7 робочих днів.');
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="567" w:right="567" w:bottom="567" w:left="1134"/></w:sectPr></w:body></w:document>';

        return $this->archive($xml);
    }

    private function paragraph(string $text, bool $bold = false): string {
        $text = strip_tags($this->productText->format($text));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','', $text) ?? '';
        $escaped = htmlspecialchars($text, ENT_QUOTES|ENT_XML1|ENT_SUBSTITUTE, 'UTF-8');

        return '<w:p><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="22"/>' . ($bold ? '<w:b/>' : '') . '</w:rPr><w:t xml:space="preserve">' . $escaped . '</w:t></w:r></w:p>';
    }

    private function row(array $cells, bool $bold = false): string {
        $xml = '<w:tr>';
        $widths = [400, 5000, 650, 1300, 1300, 1400];
        foreach ($cells as $index => $cell) {
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $widths[$index] . '" w:type="dxa"/>' . (!$bold && $index !== 1 ? '<w:noWrap/>' : '') . '</w:tcPr>' .
                $this->paragraph((string)$cell, $bold) . '</w:tc>';
        }

        return $xml . '</w:tr>';
    }

    private function archive(string $xml, array $attachments = []): string {
        $path = tempnam(sys_get_temp_dir(),'monolit-deal-doc-');
        if ($path === false) {
            throw new \RuntimeException('Не удалось создать документ.');
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($path,\ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Не удалось открыть документ.');
            }
            try {
                $files = [
                    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
                    '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
                    'word/document.xml'=>$xml,
                ] + $attachments;
                foreach ($files as $name => $contents) {
                    if (!$zip->addFromString($name, $contents)) {
                        throw new \RuntimeException('Не удалось записать документ.');
                    }
                }
            } finally {
                $closed = $zip->close();
            }
            if (!$closed) {
                throw new \RuntimeException('Не удалось сохранить документ.');
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException('Не удалось прочитать документ.');
            }

            return $contents;
        } finally {
            unlink($path);
        }
    }
}
