<?php declare(strict_types=1);

namespace App\Tests\Domain\Crm\ReadModel\Dtos;

use App\Application\Middleware\RequestDtoParser;
use App\Domain\Crm\ReadModel\Dtos\TaskFilterDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validation;

final class TaskFilterDtoTest extends TestCase {
    public function testGetFiltersAreResolvedWithDefaultsAndNullableUserIds(): void {
        $parser = new RequestDtoParser(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        $default = $parser->parseRequestToDto(Request::create('/crm/tasks'), TaskFilterDto::class);
        $parser->validate($default);
        self::assertSame('mine', $default->scope);
        self::assertSame('', $default->state);
        self::assertSame(1, $default->page);
        self::assertNull($default->executorId);
        self::assertNull($default->authorId);

        $dto = $parser->parseRequestToDto(Request::create('/crm/tasks', 'GET', [
            'scope' => 'from-me',
            'state' => 'completed',
            'executor' => '',
            'author' => '12',
            'q' => 'Текст & комментарий',
            'page' => '2',
        ]), TaskFilterDto::class);
        $parser->validate($dto);
        self::assertNull($dto->executorId);
        self::assertSame(12, $dto->authorId);
        self::assertSame(2, $dto->page);
        self::assertSame('from-me', $dto->scope);
        self::assertSame('completed', $dto->state);
        self::assertSame('Текст & комментарий', $dto->q);
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidFiltersAreRejected(array $query): void {
        $parser = new RequestDtoParser(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        $this->expectException(BadRequestHttpException::class);
        $dto = $parser->parseRequestToDto(Request::create('/crm/tasks', 'GET', $query), TaskFilterDto::class);
        $parser->validate($dto);
    }

    public static function invalidQueries(): iterable {
        yield 'unknown scope' => [['scope' => 'everyone']];
        yield 'unknown state' => [['state' => 'active']];
        yield 'negative executor' => [['executor' => '-1']];
        yield 'zero page' => [['page' => '0']];
        yield 'array instead of author' => [['author' => ['bad']]];
        yield 'array instead of search' => [['q' => ['bad']]];
    }
}
