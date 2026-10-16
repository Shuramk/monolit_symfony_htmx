<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "enterprise_phones")]
class EnterprisePhone {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "phones")]
    #[ORM\JoinColumn(name: "enterprise_profile_id", nullable: false, onDelete: "CASCADE")]
    private Enterprise $enterprise;

    #[ORM\Column(length: 20)]
    private string $kind;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(length: 32)]
    private string $number;

    public function __construct(Enterprise $enterprise, string $kind, int $position, string $number) {
        $this->enterprise = $enterprise;
        $this->kind = $kind;
        $this->position = $position;
        $this->number = $number;
    }
}
