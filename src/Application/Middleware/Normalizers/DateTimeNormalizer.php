<?php declare(strict_types=1);

namespace App\Application\Middleware\Normalizers;

use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer as SymfonyDateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface {
    private SymfonyDateTimeNormalizer $dateTimeNormalizer;
    public function __construct(array $defaultContext = []) {
        $this->dateTimeNormalizer = new SymfonyDateTimeNormalizer($defaultContext);
    }

    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \DateTimeInterface {
        if (!array_key_exists(SymfonyDateTimeNormalizer::FORMAT_KEY, $context) && is_string($data)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1) {
                $context[SymfonyDateTimeNormalizer::FORMAT_KEY] = "!Y-m-d";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data) === 1) {
                $context[SymfonyDateTimeNormalizer::FORMAT_KEY] = "!Y-m-d H:i:s";
            }
        }

        return $this->dateTimeNormalizer->denormalize($data, $type, $format, $context);
    }

    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool {
        return $this->dateTimeNormalizer->supportsDenormalization($data,  $type, $format);
    }

    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null {
        return $this->dateTimeNormalizer->normalize($data, $format, $context);
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool {
        return $this->dateTimeNormalizer->supportsNormalization($data, $format);
    }

    /**
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array {
        return [
            \DateTimeInterface::class => true,
            \DateTimeImmutable::class => true,
            \DateTime::class => true,
        ];
    }
}
