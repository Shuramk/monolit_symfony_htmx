<?php declare(strict_types=1);

namespace App\Application\Middleware;

use App\Domain\Order\Exceptions\OrderVersionConflict;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class DomainExceptionListener {
    public function __invoke(ExceptionEvent $event): void {
        $exception = $event->getThrowable();
        if ($exception instanceof OrderVersionConflict || $exception instanceof \App\Domain\Product\Exceptions\ProductVersionConflict) {
            $event->setThrowable(new ConflictHttpException($exception->getMessage(), $exception));
        } elseif ($exception instanceof \DomainException) {
            $event->setThrowable(new BadRequestHttpException($exception->getMessage(), $exception));
        }
    }
}
