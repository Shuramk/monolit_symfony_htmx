<?php declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserRole: string {
    case Main = 'ROLE_MAIN';
    case Sales = 'ROLE_SALES';
    case Production = 'ROLE_PRODUCTION';
    case ShiftSupervisor = 'ROLE_SHIFT_SUPERVISOR';
    case Partner = 'ROLE_PARTNER';

    public function title(): string {
        return match ($this) {
            self::Main=>'Главная', self::Sales=>'Продажи',
            self::Production=>'Производство', self::ShiftSupervisor=>'Старший смены', self::Partner=>'Партнёры',
        };
    }

}
