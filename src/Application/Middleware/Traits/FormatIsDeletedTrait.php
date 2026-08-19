<?php declare(strict_types=1);

namespace App\Application\Middleware\Traits;

trait FormatIsDeletedTrait {
    public function formatIsDeleted(mixed $isDeleted): bool {
        if ($isDeleted === '1' || $isDeleted === 1 || $isDeleted === 'true' || $isDeleted === true ) {
            return true;
        }

        return false;
    }
}