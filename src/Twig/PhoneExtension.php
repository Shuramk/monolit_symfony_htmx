<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PhoneExtension extends AbstractExtension {
    public function getFilters(): array {
        return [new TwigFilter('phone_format', $this->format(...))];
    }

    public function format(?string $phone): string {
        $phone = trim($phone ?? '');
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) !== 10) {
            return $phone;
        }

        return sprintf('(%s) %s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
    }
}
