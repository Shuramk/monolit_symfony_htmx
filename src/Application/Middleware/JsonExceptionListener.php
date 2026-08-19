<?php declare(strict_types=1);

namespace App\Application\Middleware;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class JsonExceptionListener {
    public function __invoke(ExceptionEvent $event): void {
        if ($event->getRequest()->getContentTypeFormat() !== "json") {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $message = $status < 500 ? $exception->getMessage() : "Не удалось выполнить запрос. Попробуйте позже.";

        $event->setResponse(new JsonResponse(["message" => $message], $status, $headers));
    }
}
