<?php declare(strict_types=1);

namespace App\Domain\Identity\Repository;

use App\Domain\Identity\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

final readonly class UserRepository {
    /** @var EntityRepository<User> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager) {
        $this->repository = $entityManager->getRepository(User::class);
    }

    public function findById(int $id): ?User {
        return $this->repository->createQueryBuilder('user')
            ->where('user.id = :id')->setParameter('id', $id)
            ->setHint(Query::HINT_REFRESH, true)->getQuery()->getOneOrNullResult();
    }

    public function findByLogin(string $login): ?User {
        return $this->repository->createQueryBuilder('user')
            ->where('user.login = :login')->setParameter('login', $login)
            ->setHint(Query::HINT_REFRESH, true)->getQuery()->getOneOrNullResult();
    }

    public function save(User $user): void {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function findForUpdate(int $id): ?User {
        return $this->repository->createQueryBuilder('user')->where('user.id = :id')->setParameter('id',$id)
            ->getQuery()->setHint(Query::HINT_REFRESH,true)->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }
}
