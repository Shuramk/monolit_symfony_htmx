<?php declare(strict_types=1);

namespace App\Application\Middleware\Normalizers;

use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EnumNormalizer implements NormalizerInterface, DenormalizerInterface {

    private BackedEnumNormalizer $backedEnumNormalizer;
    public function __construct() {
        $this->backedEnumNormalizer = new BackedEnumNormalizer();
    }

    /**
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array {
        return $this->backedEnumNormalizer->getSupportedTypes($format);
    }

    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): int|string {
        return $this->backedEnumNormalizer->normalize($data, $format, $context);
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool {
        return $this->backedEnumNormalizer->supportsNormalization($data, $format, $context);
    }

    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        return $this->backedEnumNormalizer->denormalize($data, $type, $format, $context);
    }

    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool {
        return $this->backedEnumNormalizer->supportsDenormalization($data, $type, $format, $context);
    }

}
