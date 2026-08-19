<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware;

use App\Application\Controller\Order\Dtos\OrderDraftDto;
use App\Application\Middleware\RequestDtoParser;
use App\Application\Middleware\RequestDtoResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validation;

final class RequestDtoResolverTest extends TestCase {
    public function testResolvesDtoByTypeWithoutDtoInArgumentName(): void {
        $resolver = new RequestDtoResolver(new RequestDtoParser(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
        $request = new Request([], ["markup" => "10", "lines" => [["productId" => "15", "quantity" => "2.500"]]]);
        $argument = new ArgumentMetadata("input", OrderDraftDto::class, false, false, null);

        $values = iterator_to_array($resolver->resolve($request, $argument));

        self::assertCount(1, $values);
        self::assertInstanceOf(OrderDraftDto::class, $values[0]);
        self::assertSame(15, $values[0]->lines[0]->productId);
    }
}
