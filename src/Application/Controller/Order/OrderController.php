<?php declare(strict_types=1);

namespace App\Application\Controller\Order;

use App\Application\Controller\Order\Dtos\OrderCustomerDto;
use App\Application\Controller\Order\Dtos\OrderDraftDto;
use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ReadModel\OrderDetailsView;
use App\Domain\Order\ReadModel\OrderEditView;
use App\Domain\Order\ReadModel\OrderFormView;
use App\Domain\Order\ReadModel\OrderListView;
use App\Domain\Order\ReadModel\OrderPrintView;
use App\Domain\Order\ReadModel\Dtos\OrderSearchDto;
use App\Domain\Order\Services\OrderDraftSession;
use App\Domain\Order\UseCases\CreateOrderFromDraft;
use App\Domain\Order\UseCases\Dtos\UpdateOrderDto;
use App\Domain\Order\UseCases\UpdateOrder;
use App\Domain\Order\UseCases\AddOrderPayment;
use App\Domain\Order\UseCases\Dtos\AddOrderPaymentDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression("220"))]
final class OrderController extends AbstractController {
    #[Route("/orders", name: "order_list", methods: ["GET"])]
    public function list(Request $request, OrderListView $orderListView): Response {
        $query = trim((string) $request->query->get("q", ""));
        $page = $orderListView->search(
            $query,
            $request->query->has('statuses') ? $request->query->all('statuses') : null,
            $request->query->all('managers'),
            $request->query->getInt('page', 1),
            $request->query->has('search') ? OrderSearchDto::fromArray($request->query->all('search')) : null,
            $request->query->getString('sort', 'id'),
            $request->query->getString('direction', 'DESC'),
        );

        if ($request->headers->get("HX-Request") === "true" && $request->headers->get('HX-History-Restore-Request') !== 'true') {
            return $this->render("order/_list.html.twig", ["page" => $page]);
        }

        return $this->render("order/index.html.twig", ["page" => $page]);
    }

    #[Route("/orders/{id}", name: "order_show", requirements: ["id" => "\d+"], methods: ["GET"])]
    public function show(int $id, OrderDetailsView $orderDetailsView, User $user): Response {
        $order = $orderDetailsView->find($id, $user->getRole() === UserRole::Main);
        if ($order === null) {
            throw new NotFoundHttpException("Заказ не найден.");
        }

        return $this->render("order/show.html.twig", ["order" => $order]);
    }

    #[Route('/orders/{id}/print', name: 'order_print', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function print(int $id, OrderPrintView $view): Response {
        $order = $view->find($id);
        if ($order === null) {
            throw new NotFoundHttpException('Заказ не найден.');
        }

        $response = $this->render('order/print.html.twig', ['order' => $order]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/orders/{id}/edit', name: 'order_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(int $id, OrderEditView $view): Response {
        $edit = $view->find($id);
        if ($edit === null) {
            throw new NotFoundHttpException('Заказ не найден.');
        }
        if ($edit->order['status'] === OrderStatus::Cancelled->value) {
            throw new \DomainException('Отменённый заказ нельзя редактировать.');
        }

        return $this->render('order/new.html.twig', [
            'edit' => $edit, 'productGroups' => $edit->productGroups, 'productsByParent' => $edit->productsByParent,
        ]);
    }

    #[Route('/orders/{id}', name: 'order_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, UpdateOrderDto $dto, User $user, UpdateOrder $updateOrder): Response {
        $updateOrder->execute($id, $dto, $user->getId(), new \DateTimeImmutable());

        return $this->json(['id' => $id, 'location' => $this->generateUrl('order_show', ['id' => $id])]);
    }

    #[Route('/orders/{id}/payments', name: 'order_payment_add', requirements: ['id'=>'\d+'], methods: ['POST'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('228'))]
    public function addPayment(int $id, AddOrderPaymentDto $dto, User $user, AddOrderPayment $useCase): Response {
        $paymentId = $useCase->execute($id, $dto, $user->getId(), new \DateTimeImmutable());

        return $this->json(['id'=>$paymentId, 'location'=>$this->generateUrl('order_show', ['id'=>$id])]);
    }

    #[Route('/orders/{id}/delivery-payments', name: 'order_delivery_payment_add', requirements: ['id'=>'\d+'], methods: ['POST'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('227'))]
    public function addDeliveryPayment(int $id, AddOrderPaymentDto $dto, User $user, AddOrderPayment $useCase): Response {
        $paymentId = $useCase->execute($id, $dto, $user->getId(), new \DateTimeImmutable(), shipment: true);

        return $this->json(['id'=>$paymentId, 'location'=>$this->generateUrl('order_show', ['id'=>$id])]);
    }

    #[Route("/orders/new", name: "order_new", methods: ["GET"])]
    public function new(OrderFormView $orderFormView): Response {
        $form = $orderFormView->get();

        return $this->render("order/new.html.twig", [
            "productGroups" => $form->productGroups,
            "productsByParent" => $form->productsByParent,
        ]);
    }

    #[Route("/orders/new/product-options", name: "order_new_product_options", methods: ["GET"])]
    public function productOptions(Request $request, OrderFormView $orderFormView): Response {
        $index = (string) $request->query->get("index", "0");

        return $this->render("order/_product_options.html.twig", [
            "index" => $index,
            "products" => $orderFormView->getProductOptions($request->query->getInt("parentId")),
        ]);
    }

    #[Route("/orders", name: "order_create", methods: ["POST"])]
    public function create(
        OrderCustomerDto $dto,
        User $user,
        CreateOrderFromDraft $createOrder,
    ): Response {
        $orderId = $createOrder->execute($dto, $user->getId(), new \DateTimeImmutable());

        return $this->json(["id" => $orderId, "location" => $this->generateUrl("order_show", ["id" => $orderId])], Response::HTTP_CREATED);
    }

    #[Route("/orders/draft", name: "order_draft", methods: ["POST"])]
    public function draft(OrderDraftDto $dto, OrderDraftSession $draftSession): Response {
        $draftSession->save($dto);

        return $this->json(["location" => $this->generateUrl("order_new_customer")]);
    }

    #[Route("/orders/new/customer", name: "order_new_customer", methods: ["GET"])]
    public function customer(OrderFormView $orderFormView, OrderDraftSession $draftSession): Response {
        if (!$draftSession->hasLines()) {
            return $this->redirectToRoute("order_new");
        }

        return $this->render("order/customer.html.twig", [
            "customers" => $orderFormView->getCustomers(),
            "desiredAt" => (new \DateTimeImmutable("+2 weeks"))->format("Y-m-d"),
        ]);
    }
}
