<?php declare(strict_types=1);

namespace App\Infrastructure\Event\Listener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class RequestListener {
    public function onKernelRequest(RequestEvent $event): void {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $content = $request->getContent();
        if (empty($content)) {
            return;
        }
        if (!$this->isJsonRequest($request)) {
            return;
        }
        if (!$this->transformJsonBody($request)) {
            $response = new JsonResponse('Unable to parse request', Response::HTTP_BAD_REQUEST);
            $event->setResponse($response);
        }
    }


    private function isJsonRequest(Request $request): bool {
        return 'json' === $request->getContentTypeFormat();
    }


    private function transformJsonBody(Request $request): bool {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        if ($data === null) {
            return true;
        }

        $request->request->replace($data);

        return true;
    }
}
