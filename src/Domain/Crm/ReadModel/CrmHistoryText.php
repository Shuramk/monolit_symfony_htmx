<?php declare(strict_types=1);

namespace App\Domain\Crm\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\ReadModel\LegacyProductText;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class CrmHistoryText {
    public function __construct(private Connection $connection, private LegacyProductText $productText) {}

    public function products(array $lines): string {
        return $this->describe($lines,$this->names(array_column($lines,'product_id')));
    }

    /** Convert older JSON fragments for display without rewriting stored history. */
    public function format(array $texts): array {
        $parsed = [];
        $ids = [];
        foreach ($texts as $text) {
            preg_match_all('/Продукция: (\[[^\r\n]*?\])/u',$text,$matches);
            foreach ($matches[1] as $json) {
                $lines = json_decode($json,true);
                if (!is_array($lines) || !array_is_list($lines)) {
                    continue;
                }
                foreach ($lines as $line) {
                    if (!is_array($line) || !isset($line['product_id'],$line['quantity'],$line['price'])
                        || !is_numeric($line['product_id']) || !preg_match('/^\d+(?:\.\d+)?$/D',(string)$line['quantity'])
                        || !preg_match('/^\d+(?:\.\d+)?$/D',(string)$line['price'])) {
                        continue 2;
                    }
                    $ids[] = (int)$line['product_id'];
                }
                $parsed[$json] = $lines;
            }
        }
        $names = $this->names($ids);
        $replacements = [];
        foreach ($parsed as $json=>$lines) {
            $replacements['Продукция: '.$json] = 'Продукция: '.$this->describe($lines,$names);
        }

        return array_map(static fn(string $text): string => strtr($text,$replacements),$texts);
    }

    private function names(array $ids): array {
        $ids = array_values(array_unique(array_map('intval',$ids)));
        if (!$ids) {

            return [];
        }
        $rows = $this->connection->fetchAllAssociative("SELECT p.id,CONCAT_WS(' ',g.name,p.name) name,p.unit FROM products p LEFT JOIN products g ON g.id=p.parent_id WHERE p.id IN (?)",[$ids],[ArrayParameterType::INTEGER]);

        return array_column($rows,null,'id');
    }

    private function describe(array $lines, array $names): string {
        $result = [];
        foreach ($lines as $line) {
            $product = $names[(int)$line['product_id']] ?? ['name'=>'Товар №'.$line['product_id'],'unit'=>''];
            $name = strip_tags($this->productText->format((string)$product['name']));
            $unit = strip_tags($this->productText->format((string)$product['unit']));
            $quantity = Decimal::fromString((string)$line['quantity'],3)->format();
            $price = Decimal::fromString((string)$line['price'],4)->format();
            $result[] = $name.' — '.trim($quantity.' '.$unit).' по '.$price.' грн';
        }

        return $result ? implode('; ',$result) : 'не выбрана';
    }
}
