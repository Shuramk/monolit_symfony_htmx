<?php declare(strict_types=1);

namespace App\Domain\Identity\UseCases;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Import\LegacyPermissionExpander;
use App\Domain\Identity\Repository\UserRepository;
use App\Domain\Identity\Repository\UserSettingsRepository;
use App\Domain\Identity\UseCases\Dtos\SaveUserDto;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SaveUser {
    public function __construct(private UserRepository $users, private UserSettingsRepository $settings, private LegacyPermissionExpander $expander, private UserPasswordHasherInterface $hasher) {}
    public function execute(?int $id, SaveUserDto $dto): User {
        try {
        return $this->settings->transactional(function () use ($id,$dto): User {
            $user = $id === null ? null : ($this->users->findForUpdate($id) ?? throw new NotFoundHttpException('Пользователь не найден.'));
            $login = trim($dto->login ?? $user?->getUserIdentifier() ?? '');
            $name = trim($dto->displayName ?? $user?->getDisplayName() ?? '');
            if ($login === '' || $name === '') throw new \DomainException('Заполните имя и логин пользователя.');
            $existing = $this->users->findByLogin($login);
            if ($existing !== null && $existing->getId() !== $id) throw new \DomainException('Этот логин уже занят.');
            $password = $dto->password ?? '';
            if ($password !== ($dto->passwordConfirmation ?? '')) throw new \DomainException('Пароли не совпадают.');
            if (strlen($password) > 4096) throw new \DomainException('Пароль слишком длинный.');
            if (($id === null || $password !== '') && mb_strlen($password) < 8) throw new \DomainException('Пароль должен содержать не менее 8 символов.');
            $role = $dto->role === null || $dto->role === '' ? null : (UserRole::tryFrom($dto->role) ?? throw new \DomainException('Некорректная роль.'));
            $catalogRows = $this->settings->permissions();
            $catalog = array_map('intval',array_column($catalogRows,'legacy_division_id'));
            $permissions = [];
            foreach ($dto->permissions as $permission) {
                $value = $permission['id'] ?? null;
                if ((!is_int($value) && !is_string($value)) || !ctype_digit((string)$value) || !in_array((int)$value,$catalog,true)) throw new \DomainException('Неизвестное право доступа.');
                $permissions[] = (int)$value;
            }
            $permissions = $this->expander->expand(implode(';',$permissions));
            $startPage = $dto->startPageId;
            if ($startPage === null || $startPage <= 0) throw new \DomainException('Выберите стартовую страницу.');
            $pages = array_map('intval',array_column(array_filter($catalogRows,static fn ($row): bool => (bool)$row['enabled']),'legacy_division_id'));
            if ($startPage !== null && (!in_array($startPage,$permissions,true) || !in_array($startPage,$pages,true))) throw new \DomainException('Стартовая страница должна быть доступна пользователю.');
            $user ??= new User($this->settings->nextId(),$login,$name,null,true);
            $revoke = $user->getUserIdentifier() !== $login || $password !== '' || $role === null || $dto->enabled === false;
            $user->changeDetails($login,$name,$dto->enabled ?? $user->isEnabled());
            $user->setRole($role);
            $user->setStartPageId($startPage);
            if ($password !== '') $user->changePassword($this->hasher->hashPassword($user,$password));
            $this->users->save($user);
            $this->settings->replacePermissions($user->getId(),$permissions);
            if ($revoke) $this->settings->revokeSessions($user->getId());
            return $user;
        });
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $exception) {
            throw new \DomainException('Этот логин уже занят.',previous:$exception);
        }
    }
}
