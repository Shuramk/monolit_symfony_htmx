<?php declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Middleware\Attributes\Formatter;
use App\Application\Middleware\Normalizers\DateTimeNormalizer;
use App\Application\Middleware\Normalizers\EnumNormalizer;
use App\Application\Middleware\Normalizers\FormScalarNormalizer;
use App\Application\Middleware\Normalizers\TimeZoneNormalizer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class RequestDtoParser {
    public function __construct(private ValidatorInterface $validator) {}

    public function validate(RequestDtoInterface $dto): void {
        /** @var ConstraintViolationList $errors */
        $errors = $this->validator->validate($dto);
        if ($errors->count() > 0) {
            $messages = [];

            foreach ($errors->getIterator() as $error) {
                if (is_null($error)) {
                    continue;
                }
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new BadRequestHttpException(implode("; ", array_map(
                static fn (string $path, string $message): string => $path.": ".$message,
                array_keys($messages),
                array_values($messages),
            )));
        }
    }

    /** @psalm-suppress InternalMethod */
    public function parseRequestToDto(Request $request, string $type): RequestDtoInterface {
        $page = (int) ($request->request->get('page') ?: $request->query->get('page', 1));
        $perPage = (int) (($request->request->get('perPage') ?: $request->query->get('perPage'))
            ?? ($request->request->get('per-page', 50) ?: $request->query->get('per-page', 50)));
        $perPage = $perPage > 500 ? 500 : $perPage;

        $request->request->set('page', $page);
        $request->request->set('perPage', $perPage);

        $reflection = new ReflectionClass($type);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Formatter::class);
            foreach ($attributes as $attribute) {
                /** @var Formatter $formatter */
                $formatter = $attribute->newInstance();
                $methodName = $formatter->method;
                if (method_exists($type, $methodName)) {
                    $params = [];
                    foreach ($formatter->params as $param) {
                        $params[] = $request->request->get($param);
                    }
                    if (empty($params)) {
                        $params[] = $request->request->get($property->getName());
                    }

                    $request->request->set($property->getName(), call_user_func_array([new $type, $methodName], $params));
                }
            }
        }

        $implements = class_implements($type) ?: [];
        $dtoData = key_exists(RequestDtoSortInterface::class, $implements)
            ? $request->get('sortBy', [])
            : array_merge($request->query->all(), $request->getContentTypeFormat() === "json" ? $request->toArray() : $request->request->all());

        return $this->parseArrayToDto($dtoData, $type);
    }

    public function parseArrayToDto(array $data, string $type): RequestDtoInterface {
        $serializer = new Serializer($this->getNormalizers());
        $reflection = new ReflectionClass($type);
        $dtoData = $this->normalizeAttributeNames($data);

        try {
            return $serializer->denormalize($this->castFormScalars($dtoData, $reflection), $type);
        } catch (SerializerException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }

    private function castFormScalars(array $data, ReflectionClass $reflection): array {
        $normalizer = new FormScalarNormalizer();
        $propertyTypeExtractor = $this->propertyTypeExtractor();

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (!array_key_exists($name, $data) || $data[$name] === null) {
                continue;
            }

            $propertyType = $property->getType();
            if ($propertyType instanceof ReflectionNamedType && $normalizer->supportsDenormalization($data[$name], $propertyType->getName())) {
                $data[$name] = $normalizer->denormalize($data[$name], $propertyType->getName());

                continue;
            }

            if (!is_array($data[$name])) {
                continue;
            }

            $collectionType = $propertyTypeExtractor->getType($reflection->getName(), $name);
            if (!$collectionType instanceof CollectionType) {
                continue;
            }

            $collectionValueType = $collectionType->getCollectionValueType();
            if (!$collectionValueType instanceof ObjectType) {
                continue;
            }

            $itemReflection = new ReflectionClass($collectionValueType->getClassName());
            foreach ($data[$name] as $index => $item) {
                if (is_array($item)) {
                    $data[$name][$index] = $this->castFormScalars($item, $itemReflection);
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string|int, mixed> $data
     * @return array<string|int, mixed>
     */
    private function normalizeAttributeNames(array $data): array {
        $normalized = [];

        foreach ($data as $name => $value) {
            $normalizedName = is_string($name)
                ? preg_replace_callback("/[_-]([a-z])/", static fn (array $matches): string => strtoupper($matches[1]), $name)
                : $name;

            $normalized[$normalizedName ?? $name] = is_array($value)
                ? $this->normalizeAttributeNames($value)
                : $value;
        }

        return $normalized;
    }

    private function propertyTypeExtractor(): PropertyInfoExtractor {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        return new PropertyInfoExtractor([$reflectionExtractor], [$phpDocExtractor, $reflectionExtractor], [$phpDocExtractor], [$reflectionExtractor], [$reflectionExtractor]);
    }

    private function getNormalizers(): array {
        $propertyTypeExtractor = $this->propertyTypeExtractor();

        // it is important to keep the order of normalizers, $normalizer must be always last
        return [
            new UidNormalizer(),
            new DateTimeNormalizer(),
            new TimeZoneNormalizer(),
            new EnumNormalizer(),
            new FormScalarNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, $propertyTypeExtractor),
        ];
    }
}
