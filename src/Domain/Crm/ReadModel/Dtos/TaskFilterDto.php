<?php declare(strict_types=1);

namespace App\Domain\Crm\ReadModel\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskFilterDto implements RequestDtoInterface {
    #[Assert\Positive]
    public ?int $executorId;

    #[Assert\Positive]
    public ?int $authorId;

    public function __construct(
        #[Assert\Choice(choices: ['mine', 'from-me', 'all'])]
        public string $scope = 'mine',
        #[Assert\Choice(choices: ['', 'completed', 'cancelled', 'hidden'])]
        public string $state = '',
        int|string|null $executor = null,
        int|string|null $author = null,
        #[Assert\Length(max: 20000)]
        public string $q = '',
        #[Assert\Positive]
        public int $page = 1,
    ) {
        $this->executorId = self::optionalId($executor);
        $this->authorId = self::optionalId($author);
    }

    private static function optionalId(int|string|null $value): ?int {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {

            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new \DomainException('Некорректный идентификатор пользователя в фильтре.');
        }

        return $id;
    }
}
