<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware;

use App\Application\Middleware\DomainExceptionListener;
use App\Domain\Order\Exceptions\OrderVersionConflict;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DomainExceptionListenerTest extends TestCase {
    public function testOrderVersionConflictBecomesConflictResponse(): void {
        $exception = new OrderVersionConflict('Заказ уже изменён.');
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);
        (new DomainExceptionListener())($event);

        self::assertInstanceOf(ConflictHttpException::class, $event->getThrowable());
        self::assertSame(409, $event->getThrowable()->getStatusCode());
        self::assertSame($exception, $event->getThrowable()->getPrevious());
    }

    public function testDomainRejectionBecomesBadRequest(): void {
        $exception = new \DomainException("Клиент не найден.");
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);

        (new DomainExceptionListener())($event);

        self::assertInstanceOf(BadRequestHttpException::class, $event->getThrowable());
        self::assertSame(400, $event->getThrowable()->getStatusCode());
        self::assertSame($exception, $event->getThrowable()->getPrevious());
    }

    public function testUnexpectedFailureIsNotChangedIntoUserError(): void {
        $exception = new \RuntimeException("Database unavailable");
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);

        (new DomainExceptionListener())($event);

        self::assertSame($exception, $event->getThrowable());
        self::assertFalse($event->hasResponse());
    }
}
