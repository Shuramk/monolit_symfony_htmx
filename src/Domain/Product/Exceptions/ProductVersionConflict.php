<?php declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

final class ProductVersionConflict extends \DomainException {
    public function __construct() {
        parent::__construct('Продукция уже изменена. Обновите страницу перед сохранением.');
    }
}
