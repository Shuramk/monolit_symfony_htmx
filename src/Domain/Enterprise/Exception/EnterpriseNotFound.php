<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Exception;

final class EnterpriseNotFound extends \RuntimeException {
    public function __construct(int $id) {
        parent::__construct(sprintf("Enterprise %d not found.", $id));
    }
}
