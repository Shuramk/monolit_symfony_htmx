<?php declare(strict_types=1);

namespace App\Tests\Application\Middleware;

use App\Application\Middleware\RequestDtoInterface;
use App\Application\Middleware\RequestDtoParser;
use App\Application\Controller\Order\Dtos\OrderDraftDto;
use App\Application\Controller\Order\Dtos\OrderCustomerDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class RequestDtoParserTest extends TestCase {
    public function testParsesStoredDraftWithoutAnArtificialHttpRequest(): void {
        $parser = new RequestDtoParser(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        $dto = $parser->parseArrayToDto([
            'markup' => 10,
            'lines' => [['productId' => 15, 'quantity' => '2.500', 'unitPrice' => '100.0000', 'discount' => '5.00']],
        ], OrderDraftDto::class);
        $parser->validate($dto);

        self::assertInstanceOf(OrderDraftDto::class, $dto);
        self::assertSame(10, $dto->markup);
        self::assertSame(15, $dto->lines[0]->productId);
        self::assertSame('2.500', $dto->lines[0]->quantity);
        self::assertSame('100.0000', $dto->lines[0]->unitPrice);
        self::assertSame('5.00', $dto->lines[0]->discount);
    }

    public function testCastsHtmlFormScalarsBeforeObjectDenormalization(): void {
        $dto = (new RequestDtoParser(Validation::createValidator()))->parseRequestToDto(
            new Request([], ["markup" => "10", "active" => "on", "ratio" => "1.25"]),
            FormScalarRequestDto::class,
        );

        self::assertInstanceOf(FormScalarRequestDto::class, $dto);
        self::assertSame(10, $dto->markup);
        self::assertTrue($dto->active);
        self::assertSame(1.25, $dto->ratio);
    }

    public function testNestedFormLinesKeepDecimalsAsStrings(): void {
        $dto = (new RequestDtoParser(Validation::createValidator()))->parseRequestToDto(
            new Request([], ["markup" => "10", "lines" => [["productId" => "15", "quantity" => "2.500", "unitPrice" => "100.0000"]]]),
            OrderDraftDto::class,
        );

        self::assertSame(10, $dto->markup);
        self::assertSame(15, $dto->lines[0]->productId);
        self::assertSame("2.500", $dto->lines[0]->quantity);
        self::assertSame("100.0000", $dto->lines[0]->unitPrice);
    }

    public function testJsonBodyIsReadWithoutRequestListener(): void {
        $request = Request::create("/orders", "POST", server: ["CONTENT_TYPE" => "application/json"], content: json_encode([
            "customerId" => 19, "desiredAt" => "2026-10-21T12:30:00+03:00", "shippingCost" => "25.50",
        ], JSON_THROW_ON_ERROR));
        $dto = (new RequestDtoParser(Validation::createValidator()))->parseRequestToDto($request, OrderCustomerDto::class);

        self::assertSame(19, $dto->customerId);
        self::assertSame("2026-10-21T12:30:00+03:00", $dto->desiredAt->format("c"));
        self::assertSame("25.50", $dto->shippingCost);
    }

    public function testFormDateDoesNotGainCurrentTimeOrSeconds(): void {
        $dto = (new RequestDtoParser(Validation::createValidator()))->parseRequestToDto(
            new Request([], ["customerId" => "19", "desiredAt" => "2026-10-21"]),
            OrderCustomerDto::class,
        );

        self::assertSame("2026-10-21 00:00:00", $dto->desiredAt->format("Y-m-d H:i:s"));
    }
}

final readonly class FormScalarRequestDto implements RequestDtoInterface {
    public function __construct(
        public int $markup,
        public bool $active,
        public float $ratio,
    ) {
    }
}
