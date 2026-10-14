<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Order\Enums\OrderStatus;

final readonly class OrderChangelogChanges {
    public function __construct(private LegacyProductText $productText) {}

    /** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
    public function compare(array $before, array $after): array {
        $changes = [];
        $remaining = $after['lines'];
        $matches = $this->matches($before['lines'], $after['lines']);
        foreach ($before['lines'] as $oldIndex => $old) {
            $match = $matches[$oldIndex] ?? null;
            if ($match === null) {
                $changes[] = 'Удалено: '.$this->line($old);
                continue;
            }
            $new = $remaining[$match];
            unset($remaining[$match]);
            $name = $this->name($old);
            if ($this->number($old['quantity']) !== $this->number($new['quantity'])) {
                $changes[] = 'Изменено количество: '.$name.' — '.$this->number($old['quantity']).' → '.$this->number($new['quantity']).' '.$this->productText->format($new['unit']);
            }
            if ($this->number($old['unitPrice']) !== $this->number($new['unitPrice'])) {
                $changes[] = 'Изменена цена: '.$name.' — '.$this->number($old['unitPrice']).' → '.$this->number($new['unitPrice']).' грн';
            }
            if ($this->name($old) !== $this->name($new) || $old['unit'] !== $new['unit']) {
                $changes[] = 'Изменено наименование: '.$this->name($old).' '.$this->productText->format($old['unit']).' → '.$this->name($new).' '.$this->productText->format($new['unit']);
            }
        }
        foreach ($remaining as $new) {
            $changes[] = 'Добавлено: '.$this->line($new);
        }
        $fields = [
            'Заказчик' => [$before['customer']['name'], $after['customer']['name']],
            'Телефон' => [$before['customer']['phone'], $after['customer']['phone']],
            'Адрес доставки' => [$before['delivery']['address'], $after['delivery']['address']],
            'Дата доставки' => [(new \DateTimeImmutable($before['delivery']['date']))->format('d-m-Y'), (new \DateTimeImmutable($after['delivery']['date']))->format('d-m-Y')],
            'Стоимость доставки' => [$this->number($before['delivery']['cost']).' грн', $this->number($after['delivery']['cost']).' грн'],
            'БН' => [$this->markup((int) $before['markup']), $this->markup((int) $after['markup'])],
            'Комментарий' => [$before['comments'], $after['comments']],
            'Статус' => [OrderStatus::tryFrom($before['status'])?->title() ?? $before['status'], OrderStatus::tryFrom($after['status'])?->title() ?? $after['status']],
            'Итого' => [$this->number($before['total']).' грн', $this->number($after['total']).' грн'],
        ];
        foreach ($fields as $label => [$old, $new]) {
            if ((string) $old !== (string) $new) {
                $changes[] = $label.': '.($old === null || $old === '' ? '—' : $old).' → '.($new === null || $new === '' ? '—' : $new);
            }
        }

        return $changes;
    }

    /** Reserve unchanged duplicate rows first, then pair the remaining changed rows. */
    private function matches(array $before, array $after): array {
        $matches = [];
        foreach ([true, false] as $exactOnly) {
            foreach ($before as $oldIndex => $old) {
                if (isset($matches[$oldIndex])) {
                    continue;
                }
                foreach ($after as $newIndex => $new) {
                    if (!$this->sameProduct($old, $new) || (isset($old['lineId'], $new['lineId']) && $old['lineId'] !== $new['lineId'])) {
                        continue;
                    }
                    if ($exactOnly && ($this->number($old['quantity']) !== $this->number($new['quantity']) || $this->number($old['unitPrice']) !== $this->number($new['unitPrice']))) {
                        continue;
                    }
                    $matches[$oldIndex] = $newIndex;
                    unset($after[$newIndex]);
                    break;
                }
            }
        }

        return $matches;
    }

    private function sameProduct(array $old, array $new): bool {
        if ($old['productId'] !== null || $new['productId'] !== null) {
            return $old['productId'] === $new['productId'];
        }

        return $this->name($old) === $this->name($new) && $old['unit'] === $new['unit'];
    }

    private function line(array $line): string {
        return $this->name($line).': '.$this->number($line['quantity']).' '.$this->productText->format($line['unit']).' по '.$this->number($line['unitPrice']).' грн';
    }

    private function name(array $line): string {
        return $this->productText->format(trim($line['groupName'].' '.$line['name']));
    }

    private function number(string $value): string {
        $value = Decimal::fromString($value, 4)->format();

        return rtrim(rtrim($value, '0'), '.');
    }

    private function markup(int $markup): string {
        return match (true) {
            $markup === 0 => '0%',
            $markup === 20 => 'НДС 20%',
            default => 'ФОП '.$markup.'%',
        };
    }
}
