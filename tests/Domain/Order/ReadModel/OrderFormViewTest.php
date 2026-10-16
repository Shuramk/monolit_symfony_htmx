<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\ReadModel;

use App\Domain\Customer\Repository\CustomerViewRepository;
use App\Domain\Order\ReadModel\OrderFormView;
use App\Domain\Order\ReadModel\LegacyProductText;
use App\Domain\Order\Repository\OrderViewRepository;
use App\Domain\Product\Repository\ProductCatalogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderFormViewTest extends KernelTestCase {
    public function testFormUsesProductAndCustomerDomainRepositories(): void {
        self::bootKernel();
        $container = self::getContainer();
        $view = $container->get(OrderFormView::class);
        $products = $container->get(ProductCatalogRepository::class);
        $customers = $container->get(CustomerViewRepository::class);

        $form = $view->get();
        self::assertNotEmpty($form->productGroups);
        self::assertSame($products->findProductGroups(), $form->productGroups);
        $expected = $products->findProductsByParent();
        $text = new LegacyProductText();
        foreach ($expected as &$variants) {
            foreach ($variants as &$variant) {
                $variant['name'] = $text->format($variant['name']);
                $variant['unit'] = $text->format($variant['unit'] ?? '');
            }
            unset($variant);
        }
        unset($variants);
        self::assertSame($expected, $form->productsByParent);
        self::assertSame($customers->findCustomers(), $view->getCustomers());

        foreach ($form->productGroups as $group) {
            self::assertSame($products->findProductsByParentId((int) $group['id']), $view->getProductOptions((int) $group['id']));
        }
    }

    public function testUnknownOrderHasNoEditSource(): void {
        self::bootKernel();

        self::assertNull(self::getContainer()->get(OrderViewRepository::class)->findForEdit(-1));
    }
}
