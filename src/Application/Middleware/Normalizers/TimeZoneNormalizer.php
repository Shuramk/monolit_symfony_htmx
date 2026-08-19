<?php declare(strict_types=1);

namespace App\Application\Middleware\Normalizers;

use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TimeZoneNormalizer implements NormalizerInterface, DenormalizerInterface {
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): int|string {
        if (!$data instanceof DateTimeZone) {
            throw new InvalidArgumentException('The data must belong to a ' . DateTimeZone::class);
        }

        return $data->getName();
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool {
        return $data instanceof DateTimeZone;
    }

    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        if ($type !== DateTimeZone::class) {
            throw new InvalidArgumentException('The type of the data must be ' . DateTimeZone::class);
        }

        if (!is_string($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data is not compatible with DateTimeZone' , $data, [], $context['deserialization_path'] ?? null, true);
        }
        try {
            return new DateTimeZone($data);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('The provided timmeZone is non valid');
        }
    }

    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool {
        return $type === DateTimeZone::class;
    }

    /**
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array {
        return [
            \DateTimeZone::class => true,
        ];
    }
}
