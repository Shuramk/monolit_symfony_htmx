<?php declare(strict_types=1);

namespace App\Domain\Identity\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use App\Domain\Identity\Enums\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveUserDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Choice(choices:['',UserRole::Main->value,UserRole::Sales->value,UserRole::Production->value,UserRole::ShiftSupervisor->value,UserRole::Partner->value],message:'Некорректная роль.')]
        public ?string $role,
        #[Assert\Count(max:1000)]
        #[Assert\All(constraints:[new Assert\Collection(fields:['id'=>[new Assert\Regex(pattern:'/^\d+$/')]])])]
        public array $permissions = [],
        #[Assert\NotBlank(allowNull:true)]
        #[Assert\Length(max:30)]
        public ?string $login = null,
        #[Assert\NotBlank(allowNull:true)]
        #[Assert\Length(max:255)]
        public ?string $displayName = null,
        #[Assert\Length(max:4096)]
        public ?string $password = null,
        #[Assert\EqualTo(propertyPath:'password',message:'Пароли не совпадают.')]
        public ?string $passwordConfirmation = null,
        public ?bool $enabled = null,
        #[Assert\NotNull(message:'Выберите стартовую страницу.')]
        #[Assert\Positive(message:'Выберите стартовую страницу.')]
        public ?int $startPageId = null,
    ) {}
}
