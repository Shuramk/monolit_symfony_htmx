<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware\Normalizers;

use App\Application\Middleware\Normalizers\FormScalarNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class FormScalarNormalizerTest extends TestCase {
    #[DataProvider("formValues")]
    public function testConvertsSupportedFormValues(string $type, string $value, int|float|bool $expected): void {
        self::assertSame($expected, (new FormScalarNormalizer())->denormalize($value, $type));
    }

    public static function formValues(): iterable {
        yield ["int", "0", 0];
        yield ["int", "123", 123];
        yield ["int", "-12", -12];
        yield ["float", "0", 0.0];
        yield ["float", "12.5", 12.5];
        yield ["float", "-0.125", -0.125];
        yield ["bool", "on", true];
        yield ["bool", "true", true];
        yield ["bool", "0", false];
        yield ["bool", "off", false];
    }

    #[DataProvider("invalidFormValues")]
    public function testRejectsLossyConversions(string $type, string $value): void {
        $this->expectException(NotNormalizableValueException::class);

        (new FormScalarNormalizer())->denormalize($value, $type);
    }

    public static function invalidFormValues(): iterable {
        yield ["int", "12.5"];
        yield ["int", "12abc"];
        yield ["float", "12,5"];
        yield ["float", "12abc"];
        yield ["bool", "2"];
        yield ["bool", "maybe"];
    }
}
