<?php declare(strict_types=1);

namespace App\Application\Middleware;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class CsrfTokenListener {
    public const TOKEN_ID = "session";

    public function __construct(private Security $security, private CsrfTokenManagerInterface $csrfTokenManager) {
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER, priority: 100)]
    public function onController(ControllerEvent $event): void {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->isMethodSafe()
            || $this->security->getFirewallConfig($request)?->isStateless() !== false
            || $this->security->getUser() === null
        ) {
            return;
        }

        $token = $request->getPayload()->get("_token") ?? $request->headers->get("X-CSRF-Token");
        if (!is_string($token) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw new InvalidCsrfTokenException("Invalid CSRF token.");
        }
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
    public function onException(ExceptionEvent $event): void {
        if (!$event->getThrowable() instanceof InvalidCsrfTokenException) {
            return;
        }

        $message = "Некорректный CSRF-токен. Обновите страницу и повторите отправку.";
        $response = $event->getRequest()->getContentTypeFormat() === "json"
            ? new JsonResponse(["message" => $message], Response::HTTP_FORBIDDEN)
            : new Response($message, Response::HTTP_FORBIDDEN);

        $event->setResponse($response);
    }
}
