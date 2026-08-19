<?php declare(strict_types=1);

namespace App\Infrastructure\Event\Listener;

use App\Exception\DomainException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ExceptionListener {
    private Throwable $exception;

    public function __construct(private readonly LoggerInterface $logger) {}

    public function onKernelException(ExceptionEvent $event): void {
        $this->exception = $event->getThrowable();

        if ($this->isDomainException()) {
            $this->writeLog();
            /** @psalm-suppress NoInterfaceProperties */
            $response = $this->exception->asString ? $this->createStringResponse(Response::HTTP_BAD_REQUEST) : $this->createErrorResponseObject();
            $event->setResponse($response);
        } elseif (
            $this->exception instanceof NotFoundHttpException
            || $this->exception instanceof NoResultException
            || $this->exception instanceof EntityNotFoundException
        ) {
            $response = $this->createObjectResponseNotFound();
            $event->setResponse($response);
        } elseif ($this->isProduction()) {
            $this->writeLog();
            $response = $this->createStringResponse();
            $event->setResponse($response);
        }
    }

    private function createStringResponse(?int $statusCode = null): JsonResponse {
        return new JsonResponse($this->exception->getMessage(), $statusCode ?: $this->getStatusCode());
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    private function createErrorResponseObject(): JsonResponse {
        $errorObject = [
            'type' => $this->exception->getType(),
            'message' => $this->exception->getMessage(),
            'data' => $this->exception->getData(),
        ];
        return new JsonResponse($errorObject, $this->exception->getCode());
    }

    private function getStatusCode(): int {
        if ($this->exception->getCode() <= 500) {
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return (int) ($this->exception->getCode() ?: Response::HTTP_BAD_REQUEST);
    }

    private function isProduction(): bool {
        return !$_ENV['APP_DEBUG'];
    }

    private function writeLog(): void {
        $extraData = $this->exception instanceof DomainException ? $this->exception->getIndexedData() : [];
        $this->logger->critical($this->exception->getMessage(), array_merge($extraData, $this->exception->getTrace()));
    }

    private function isDomainException(): bool {
        return $this->exception instanceof DomainException;
    }

    private function createObjectResponseNotFound(): JsonResponse {
        return new JsonResponse($this->exception->getMessage(), Response::HTTP_NOT_FOUND);
    }
}
