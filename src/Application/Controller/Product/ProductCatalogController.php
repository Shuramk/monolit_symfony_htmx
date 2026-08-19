<?php

declare(strict_types=1);

namespace App\Application\Controller\Product;

use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Product\ReadModel\ProductCatalogView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductCatalogController extends AbstractController {
    #[Route("/products", name: "product_catalog", methods: ["GET"])]
    public function __invoke(Request $request, ProductCatalogView $productCatalogView): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 1010);
        $query = trim((string) $request->query->get("q", ""));
        $includeDisabled = $request->query->getBoolean("include_disabled");
        $products = $productCatalogView->search($query, $includeDisabled);

        if ($request->headers->get("HX-Request") === "true") {
            return $this->render("product/_rows.html.twig", ["products" => $products]);
        }

        return $this->render("product/index.html.twig", ["products" => $products, "query" => $query, "includeDisabled" => $includeDisabled]);
    }
}
