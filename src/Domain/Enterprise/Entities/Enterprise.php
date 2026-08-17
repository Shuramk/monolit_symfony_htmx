<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "enterprise_profiles")]
final class Enterprise {
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private int $id;

    #[ORM\Column(name: "display_name", length: 255, nullable: true)]
    private ?string $displayName = null;

    /** @var Collection<int, EnterprisePhone> */
    #[ORM\OneToMany(mappedBy: "enterprise", targetEntity: EnterprisePhone::class, cascade: ["persist"], orphanRemoval: true)]
    private Collection $phones;

    public function __construct(int $id) {
        $this->id = $id;
        $this->phones = new ArrayCollection();
    }
}
