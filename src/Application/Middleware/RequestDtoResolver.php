<?php declare(strict_types=1);

namespace App\Application\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class RequestDtoResolver implements ValueResolverInterface {
    public function __construct(
        private RequestDtoParser $requestDtoParser,
    ) {}

    #[\Override]
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator {
        $type = $argument->getType();
        if ($type === null || !is_a($type, RequestDtoInterface::class, true)) {
            return [];
        }

        $dto = $this->requestDtoParser->parseRequestToDto($request, $type);
        $this->requestDtoParser->validate($dto);

        yield $dto;
    }
}
