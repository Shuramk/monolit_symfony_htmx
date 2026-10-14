<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware;

use App\Application\Middleware\JsonExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class JsonExceptionListenerTest extends TestCase {
    public function testValidationMessageIsReturnedAsJsonWithCorrectStatus(): void {
        $event = $this->event(new BadRequestHttpException("Неверные данные."));
        (new JsonExceptionListener())($event);

        self::assertSame(400, $event->getResponse()->getStatusCode());
        self::assertSame("application/json", $event->getResponse()->headers->get("Content-Type"));
        self::assertSame(["message" => "Неверные данные."], json_decode($event->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testInternalExceptionRemains500WithoutLeakingDetails(): void {
        $event = $this->event(new \RuntimeException("SQL credentials must not be exposed"));
        (new JsonExceptionListener())($event);

        self::assertSame(500, $event->getResponse()->getStatusCode());
        self::assertStringNotContainsString("SQL credentials", $event->getResponse()->getContent());
    }

    public function testHtmlExceptionIsLeftToStandardHandler(): void {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, new \RuntimeException());
        (new JsonExceptionListener())($event);

        self::assertFalse($event->hasResponse());
    }

    private function event(\Throwable $exception): ExceptionEvent {
        $request = Request::create("/orders", "POST", server: ["CONTENT_TYPE" => "application/json"]);

        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
