<?php declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class PaginationTest extends TestCase {
    private function render(int $current, int $pages, array $options = []): Crawler {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('path', static fn(string $route, array $params): string => '/'.$route.'?'.http_build_query($params)));
        return new Crawler($twig->render('components/_pagination.html.twig', array_replace([
            'current' => $current, 'pages' => $pages, 'route' => 'orders', 'params' => [], 'label' => 'Страницы заказов',
        ], $options)));
    }

    public function testFirstAndLastPagesDisableOnlyUnavailableDirection(): void {
        foreach ([1 => 'prev', 64 => 'next'] as $page => $disabled) {
            $html = $this->render($page, 64);
            self::assertSame((string)$page, $html->filter('[aria-current="page"]')->text());
            self::assertCount(1, $html->filter('[data-direction="'.$disabled.'"][aria-disabled="true"]'));
            self::assertCount(0, $html->filter('a[data-direction="'.$disabled.'"]'));
            self::assertCount(1, $html->filter('a[rel]'));
        }
    }

    public function testWindowHasFirstLastEllipsesAndPreservesFiltersAndHtmx(): void {
        $params = ['q' => 'Алекс', 'statuses' => ['new', 'ready'], 'search' => ['phone' => '067'], 'sort' => 'createdAt', 'direction' => 'ASC'];
        $html = $this->render(32, 64, ['params' => $params, 'target' => '#order-list']);
        self::assertSame(['1','30','31','32','33','34','64'], $html->filter('[data-page]')->each(static fn(Crawler $node) => $node->text()));
        self::assertCount(2, $html->filter('.pagination-ellipsis'));
        self::assertSame('#order-list', $html->filter('nav')->attr('hx-target'));
        parse_str(parse_url($html->filter('a[rel="next"]')->attr('href'), PHP_URL_QUERY), $query);
        self::assertSame(array_replace($params, ['page' => '33']), $query);
    }

    public function testSmallPageCountsHaveNoGapsAndSinglePageIsHidden(): void {
        self::assertCount(0, $this->render(1, 1)->filter('nav'));
        self::assertCount(0, $this->render(1, 0)->filter('nav'));
        $html = $this->render(2, 4);
        self::assertSame(['1','2','3','4'], $html->filter('[data-page]')->each(static fn(Crawler $node) => $node->text()));
        self::assertCount(0, $html->filter('.pagination-ellipsis'));
        self::assertNull($html->filter('nav')->attr('hx-target'));
    }

    public function testEveryWindowIsOrderedUniqueBoundedAndIncludesEndpoints(): void {
        foreach ([2, 7, 8, 64] as $pages) {
            for ($current = 1; $current <= $pages; ++$current) {
                $html = $this->render($current, $pages);
                $numbers = $html->filter('[data-page]')->each(static fn(Crawler $node) => (int)$node->text());
                $ordered = array_values(array_unique($numbers));
                sort($ordered);
                self::assertSame($ordered, $numbers);
                self::assertSame(1, $numbers[0]);
                self::assertSame($pages, $numbers[array_key_last($numbers)]);
                self::assertContains($current, $numbers);
                self::assertLessThanOrEqual(9, count($numbers));
                foreach ($html->filter('a')->each(static fn(Crawler $node) => $node->attr('href')) as $href) {
                    parse_str(parse_url($href, PHP_URL_QUERY), $query);
                    self::assertGreaterThanOrEqual(1, (int)$query['page']);
                    self::assertLessThanOrEqual($pages, (int)$query['page']);
                }
            }
        }
    }
}
