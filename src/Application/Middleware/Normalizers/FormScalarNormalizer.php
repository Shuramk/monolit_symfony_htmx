<?php declare(strict_types=1);

namespace App\Application\Middleware\Normalizers;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class FormScalarNormalizer implements DenormalizerInterface {
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): int|float|bool {
        return match ($type) {
            "int" => $this->toInt($data, $context),
            "bool" => $this->toBool($data, $context),
            "float" => $this->toFloat($data, $context),
        };
    }

    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool {
        return $type === "int" || $type === "float" || $type === "bool";
    }

    /** @return array<string, bool|null> */
    #[\Override]
    public function getSupportedTypes(?string $format): array {
        return [
            "int" => true,
            "bool" => true,
            "float" => true,
        ];
    }

    private function toInt(mixed $data, array $context): int {
        if (is_int($data)) {
            return $data;
        }

        if (!is_string($data) || preg_match("/^-?(?:0|[1-9][0-9]*)$/", trim($data)) !== 1) {
            throw $this->invalid("целое число", $data, $context);
        }

        $value = filter_var(trim($data), FILTER_VALIDATE_INT);
        if ($value === false && trim($data) !== "0") {
            throw $this->invalid("целое число", $data, $context);
        }

        return $value;
    }

    private function toFloat(mixed $data, array $context): float {
        if (is_float($data) || is_int($data)) {
            return (float) $data;
        }

        if (!is_string($data) || preg_match("/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/", trim($data)) !== 1) {
            throw $this->invalid("десятичное число", $data, $context);
        }

        return (float) trim($data);
    }

    private function toBool(mixed $data, array $context): bool {
        if (is_bool($data)) {
            return $data;
        }

        if (!is_string($data)) {
            throw $this->invalid("логическое значение", $data, $context);
        }

        return match (strtolower(trim($data))) {
            "1", "true", "on", "yes" => true,
            "0", "false", "off", "no" => false,
            default => throw $this->invalid("логическое значение", $data, $context),
        };
    }

    private function invalid(string $expectedType, mixed $data, array $context): NotNormalizableValueException {
        return NotNormalizableValueException::createForUnexpectedDataType(
            sprintf("Ожидалось %s.", $expectedType),
            $data,
            ["string"],
            $context["deserialization_path"] ?? null,
            true,
        );
    }
}
