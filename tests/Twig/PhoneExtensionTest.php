<?php declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\PhoneExtension;
use PHPUnit\Framework\TestCase;

final class PhoneExtensionTest extends TestCase {
    public function testFormatsLocalAndInternationalUkrainianNumbersWithoutDamagingOtherValues(): void {
        $formatter = new PhoneExtension();
        foreach (['0673265216', '+380673265216', '(067) 326-52-16'] as $phone) {
            self::assertSame('(067) 326-52-16', $formatter->format($phone));
        }
        self::assertSame('(051) 234-56-78', $formatter->format('0512345678'));
        self::assertSame('12345', $formatter->format('12345'));
        self::assertSame('+48 123 456 789', $formatter->format('+48 123 456 789'));
        self::assertSame('', $formatter->format(null));
        self::assertSame('', $formatter->format(' '));
    }
}
