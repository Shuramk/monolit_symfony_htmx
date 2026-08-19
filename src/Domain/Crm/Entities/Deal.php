<?php declare(strict_types=1);
namespace App\Domain\Crm\Entities;

use App\Domain\Common\ValueObjects\Decimal;

final class Deal {
    public function __construct(public readonly int $id, public readonly ?int $authorId, private string $state, private ?int $orderId, public readonly int $version) {}
    public static function fromRow(array $row): self { return new self((int)$row['id'],$row['author_id'] === null ? null : (int)$row['author_id'],$row['state'],$row['converted_order_id'] === null ? null : (int)$row['converted_order_id'],(int)$row['version']); }
    public function state(): string { return $this->state; }
    public function orderId(): ?int { return $this->orderId; }
    public function assertEditable(): void { if ($this->state !== 'active') throw new \DomainException('Редактировать можно только активную сделку.'); }
    public function cancel(string $comment, int $type): void {
        $this->assertEditable();
        if (trim($comment) === '' && $type === 0) throw new \DomainException('Укажите причину отмены.');
        if ($type < 0 || $type > 4) throw new \DomainException('Неизвестная причина отмены.');
        $this->state = 'cancelled';
    }
    public function hide(): void { if ($this->state !== 'cancelled') throw new \DomainException('Скрыть можно только отменённую сделку.'); $this->state = 'hidden'; }
    public function restore(): void { if (!in_array($this->state,['cancelled','hidden'],true)) throw new \DomainException('Сделка не отменена.'); $this->state = 'active'; }
    public function converted(int $orderId): void { $this->assertEditable(); $this->state = 'converted'; $this->orderId = $orderId; }
    public static function decimal(string $value, int $scale): Decimal {
        $value = str_replace(',','.',trim($value));
        if (!preg_match('/^\d{1,8}(?:\.\d{1,'.$scale.'})?$/D',$value)) throw new \DomainException('Некорректная сумма или количество.');
        return Decimal::fromString($value,$scale);
    }
    public static function date(?string $value, bool $dateOnly = false): ?string {
        if ($value === null || trim($value) === '') return null;
        $value = str_replace('T',' ',trim($value));
        $format = $dateOnly ? 'Y-m-d' : (strlen($value) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s');
        $date = \DateTimeImmutable::createFromFormat('!'.$format,$value,new \DateTimeZone('Europe/Kyiv'));
        if (!$date || $date->format($format) !== $value) throw new \DomainException('Некорректная дата.');
        return $date->format($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s');
    }
}
