<?php declare(strict_types=1);

namespace App\Application\Controller\Warehouse;

use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Warehouse\ReadModel\WarehouseView;
use App\Domain\Warehouse\Security\WarehouseInventoryVoter;
use App\Domain\Identity\Entities\User;
use App\Domain\Warehouse\UseCases\ChangeStock;
use App\Domain\Warehouse\UseCases\SetMinimum;
use App\Domain\Warehouse\UseCases\ProductionTasks;
use App\Domain\Warehouse\UseCases\Dtos\ChangeStockDto;
use App\Domain\Warehouse\UseCases\Dtos\SetMinimumDto;
use App\Domain\Warehouse\UseCases\Dtos\CreateProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\CompleteProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\AcceptProductionDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('101'))]
final class WarehouseController extends AbstractController {
    #[Route('/warehouse/products', name: 'warehouse_products', methods: ['GET'])]
    public function index(Request $request, WarehouseView $view): Response {
        if ($request->query->getInt('groupId') > 0) {
            return $this->redirectToRoute('warehouse_group', ['id' => $request->query->getInt('groupId')]);
        }
        return $this->render('warehouse/index.html.twig', ['view' => $view->overview()]);
    }

    #[Route('/warehouse/products/{id}', name: 'warehouse_group', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function group(int $id, Request $request, WarehouseView $view): Response {
        return $this->render('warehouse/group.html.twig', ['view' => $view->details($id) ?? throw $this->createNotFoundException()]);
    }

    #[Route('/warehouse/variants/{id}/add', name: 'warehouse_add', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_ADD')]
    public function add(int $id, ChangeStockDto $dto, User $user, ChangeStock $change): Response {
        return $this->json($change->execute($id,$dto,$user->getId(),true,$user->canSeeWarehouseBreakdown()));
    }

    #[Route('/warehouse/variants/{id}/delete', name: 'warehouse_delete', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_DELETE')]
    public function delete(int $id, ChangeStockDto $dto, User $user, ChangeStock $change): Response {
        return $this->json($change->execute($id,$dto,$user->getId(),false,$user->canSeeWarehouseBreakdown()));
    }

    #[Route('/warehouse/variants/{id}/minimum', name: 'warehouse_minimum', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_MINIMUM')]
    public function minimum(int $id, SetMinimumDto $dto, User $user, SetMinimum $minimum): Response {
        return $this->json($minimum->execute($id,$dto,$user->getId()));
    }

    #[Route('/warehouse/variants/{id}/history', name: 'warehouse_history', requirements: ['id'=>'\\d+'], methods: ['GET'])]
    public function history(int $id, Request $request, WarehouseView $view): Response {
        return $this->render('warehouse/history.html.twig', ['view'=>$view->history($id,$request->query->getInt('page',1)) ?? throw $this->createNotFoundException()]);
    }

    #[Route('/warehouse/products/{id}/need/print', name: 'warehouse_need_print', requirements: ['id'=>'\\d+'], methods: ['GET'])]
    public function printNeed(int $id, Request $request, WarehouseView $view): Response {
        $details = $view->details($id) ?? throw $this->createNotFoundException();
        $ids = array_map('intval', $request->query->all('products'));
        $rows = array_values(array_filter($details->products, static fn ($row): bool => in_array((int)$row['id'],$ids,true)));
        $response = $this->render('warehouse/need_print.html.twig', ['group'=>$details->group,'products'=>$rows]);
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }

    #[Route('/warehouse/products/{id}/need', name: 'warehouse_need', requirements: ['id'=>'\\d+'], methods: ['GET'])]
    public function need(int $id, WarehouseView $view): Response {
        $response = $this->json(['products'=>$view->needed($id) ?? throw $this->createNotFoundException()]);
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }

    #[Route('/warehouse/products/{id}/production', name: 'warehouse_production', requirements: ['id'=>'\\d+'], methods: ['GET'])]
    public function production(int $id, WarehouseView $view): Response {
        return $this->render('warehouse/production.html.twig', ['view'=>$view->production($id) ?? throw $this->createNotFoundException()]);
    }

    #[Route('/warehouse/products/{id}/production', name: 'warehouse_production_create', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_PRODUCTION')]
    public function createProduction(int $id, CreateProductionDto $dto, User $user, ProductionTasks $production): Response {
        return $this->json(['ids'=>$production->create($id,$dto,$user->getId()),'location'=>$this->generateUrl('warehouse_production',['id'=>$id])],Response::HTTP_CREATED);
    }

    #[Route('/warehouse/production/{id}/complete', name: 'warehouse_production_complete', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_PRODUCTION')]
    public function completeProduction(int $id, CompleteProductionDto $dto, User $user, ProductionTasks $production): Response {
        return $this->json(['entryId'=>$production->complete($id,$dto,$user->getId())]);
    }

    #[Route('/warehouse/production/entries/{id}/accept', name: 'warehouse_production_accept', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_ACCEPT')]
    public function acceptProduction(int $id, AcceptProductionDto $dto, User $user, ProductionTasks $production): Response {
        return $this->json($production->accept($id,$dto,$user->getId(),$user->canSeeWarehouseBreakdown()));
    }

    #[Route('/warehouse/production/entries/{id}/close', name: 'warehouse_production_close', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    #[IsGranted('WAREHOUSE_ACCEPT')]
    public function closeProduction(int $id, User $user, ProductionTasks $production): Response {
        $production->closeCover($id,$user->getId());
        return $this->json(['closed'=>true]);
    }

    #[Route('/warehouse/products/inventory/{type}', name: 'warehouse_inventory', requirements: ['type' => '[12]'], methods: ['GET'])]
    #[IsGranted(WarehouseInventoryVoter::ATTRIBUTE)]
    public function inventory(int $type, WarehouseView $view): Response {
        $response = $this->render('warehouse/inventory.html.twig', ['view' => $view->inventory($type)]);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
