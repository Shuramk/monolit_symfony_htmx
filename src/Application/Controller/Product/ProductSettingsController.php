<?php declare(strict_types=1);

namespace App\Application\Controller\Product;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Product\ReadModel\ProductSettingsView;
use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Product\UseCases\SaveProduct;
use App\Domain\Product\UseCases\Dtos\UpdateProductSortingDto;
use App\Domain\Product\UseCases\Dtos\UpdateProductVariantsDto;
use App\Domain\Product\UseCases\UpdateProductSorting;
use App\Domain\Product\UseCases\UpdateProductVariants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('1010'))]
#[Route('/settings/products')]
final class ProductSettingsController extends AbstractController {
    #[Route('/sorting', name: 'product_settings_sorting', methods: ['POST'])]
    public function sorting(UpdateProductSortingDto $dto, User $user, UpdateProductSorting $update): Response {
        return $this->json(['versions' => $update->execute($dto, $user->getId()), 'location' => $this->generateUrl('product_settings')]);
    }

    #[Route('/{id}/variants', name: 'product_settings_variants', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function variants(int $id, UpdateProductVariantsDto $dto, User $user, UpdateProductVariants $update): Response {
        return $this->json(['versions' => $update->execute($id, $dto, $user->getId()), 'location' => $this->generateUrl('product_settings_edit', ['id' => $id])]);
    }

    #[Route('', name: 'product_settings', methods: ['GET'])]
    public function index(Request $request, ProductSettingsView $view): Response {
        $query = trim($request->query->getString('q'));
        $hidden = $request->query->getBoolean('includeDisabled');
        return $this->render('product/settings.html.twig', ['view' => $view->page($query, $hidden)]);
    }

    #[Route('/new', name: 'product_settings_new', methods: ['GET'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('1011'))]
    public function new(Request $request, ProductSettingsView $view): Response {
        $parentId = $request->query->getInt('parentId');
        return $this->render('product/settings_edit.html.twig', ['view' => $view->new($parentId === 0 ? null : $parentId) ?? throw $this->createNotFoundException()]);
    }

    #[Route('/new/{parentId}', name: 'product_settings_create', requirements: ['parentId' => '\\d+'], methods: ['POST'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('1011'))]
    public function create(int $parentId, SaveProductDto $dto, User $user, SaveProduct $save): Response {
        $id = $save->execute($dto, $user->getId(), parentId: $parentId === 0 ? null : $parentId);
        return $this->json(['location' => $this->generateUrl('product_settings_edit', ['id' => $id])], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'product_settings_edit', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function edit(int $id, ProductSettingsView $view): Response {
        return $this->render('product/settings_edit.html.twig', ['view' => $view->edit($id) ?? throw $this->createNotFoundException()]);
    }

    #[Route('/{id}', name: 'product_settings_save', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function save(int $id, SaveProductDto $dto, User $user, SaveProduct $save): Response {
        $save->execute($dto, $user->getId(), $id);
        return $this->json(['location' => $this->generateUrl('product_settings_edit', ['id' => $id])]);
    }
}
