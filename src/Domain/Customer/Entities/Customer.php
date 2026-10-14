<?php

declare(strict_types=1);

namespace App\Domain\Customer\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(name: 'IDX_CUSTOMERS_NAME', columns: ['name'])]
#[ORM\Index(name: 'IDX_CUSTOMERS_PHONE', columns: ['phone'])]
class Customer {
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    private int $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone;

    #[ORM\Column(name: 'phone_secondary', length: 32, nullable: true)]
    private ?string $phoneSecondary = null;

    #[ORM\Column(name: 'phone_tertiary', length: 32, nullable: true)]
    private ?string $phoneTertiary = null;

    #[ORM\Column(name: 'registered_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column(name: 'person_type', length: 10, nullable: true)]
    private ?string $personType = null;

    #[ORM\Column(name: 'contact_person', length: 100, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(name: 'buyer_type', length: 10, nullable: true)]
    private ?string $buyerType = null;

    #[ORM\Column(name: 'stacker_id', type: 'bigint', nullable: true)]
    private ?int $stackerId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(int $id, string $name, ?string $phone, \DateTimeImmutable $now) {
        $this->id = $id;
        $this->name = $name;
        $this->phone = $phone;
        $this->registeredAt = $this->importedAt = $this->updatedAt = $now;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPhone(): ?string {
        return $this->phone;
    }
}
