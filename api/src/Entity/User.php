<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\AppCode;
use App\Repository\UserRepository;
use App\Security\Roles;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person (alive, dead, undead, or fictional).
 *
 * @see https://schema.org/Person
 */
// #[ApiResource(
//    types: ['https://schema.org/Person'],
//    operations: [
//        new GetCollection(
//            uriTemplate: '/admin/users{._format}',
//            paginationClientItemsPerPage: true,
//            security: 'is_granted("OIDC_ADMIN")',
//            filters: ['app.filter.user.admin.name'],
//            itemUriTemplate: '/admin/users/{id}{._format}'
//        ),
//        new Get(
//            uriTemplate: '/admin/users/{id}{._format}',
//            security: 'is_granted("OIDC_ADMIN")'
//        ),
//        new Get(
//            uriTemplate: '/users/{id}{._format}',
//            security: 'object === user'
//        ),
//    ],
//    normalizationContext: [
//        AbstractNormalizer::GROUPS => ['user.read'],
//        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
//    ]
// )]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Get(security: "object.getId() == user.getId() or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "object.getId() == user.getId() or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('email')]
class User implements UserInterface, AccountAwareInterface
{
    /**
     * @see https://schema.org/identifier
     */
    #[ApiProperty(types: ['https://schema.org/identifier'])]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    /**
     * @see https://schema.org/email
     */
    #[Assert\NotBlank]
    #[Assert\Email]
    #[ORM\Column(unique: true)]
    public string $email;

    public string|Uuid|null $sub = null;

    /**
     * @see https://schema.org/givenName
     */
    #[ApiProperty(types: ['https://schema.org/givenName'])]
    #[Groups(groups: ['user.read'])]
    #[ORM\Column]
    public string $firstName;

    /**
     * @see https://schema.org/familyName
     */
    #[ApiProperty(types: ['https://schema.org/familyName'])]
    #[Groups(groups: ['user.read'])]
    #[ORM\Column]
    public string $lastName;

    #[ORM\Column(type: 'string', length: 19, nullable: true)]
    #[Assert\Regex('/\d{4}-\d{4}-\d{4}-\d{3}[0-9X]/')]
    private ?string $orcid = null;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'users')]
    #[Groups(groups: ['user.read'])]
    #[MaxDepth(1)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    /**
     * @var Collection<int, ConnectedApp>
     */
    #[ORM\OneToMany(targetEntity: ConnectedApp::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $connectedApps;

    /**
     * @var array<string>
     */
    #[Assert\All(constraints: [new Assert\Choice(callback: [Roles::class, 'getRoles'])])]
    #[ORM\Column(type: 'json', options: ['jsonb' => true, 'default' => '[]'])]
    private array $roles = [];

    public function __construct()
    {
        $this->connectedApps = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return !empty($this->email) ? $this->email : 'NO_EMAIL';
    }

    /**
     * @see https://schema.org/name
     */
    #[ApiProperty(iris: ['https://schema.org/name'])]
    #[Groups(groups: ['user.read'])]
    public function getName(): ?string
    {
        if (!$this->firstName && !$this->lastName) {
            return null;
        }

        return trim(\sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getOrcid(): ?string
    {
        return $this->orcid;
    }

    public function setOrcid(?string $orcid): self
    {
        $this->orcid = $orcid;

        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function hasAccount(): bool
    {
        return isset($this->account);
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_unique(array_merge([Roles::ROLE_USER], $roles));

        return $this;
    }

    public function addRole(string $role): self
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    /**
     * @param Collection<int, ConnectedApp> $connectedApps
     */
    public function setConnectedApps(Collection $connectedApps): self
    {
        $this->connectedApps = $connectedApps;

        return $this;
    }

    public function getConnectedAppByCode(AppCode $code): ?ConnectedApp
    {
        $apps = $this->connectedApps->filter(static fn (ConnectedApp $connectedApp) => $connectedApp->getCode()->value === $code->value);
        if (2 <= $apps->count()) {
            throw new \LogicException(\sprintf('User has more than one connected app with code %s', $code->value));
        }
        if (1 === $apps->count()) {
            $app = $apps->first();

            if ($app) {
                return $app;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, ConnectedApp>
     */
    public function getConnectedApps(): Collection
    {
        return $this->connectedApps;
    }

    public function addConnectedApp(ConnectedApp $connectedApp): self
    {
        if (!$this->connectedApps->contains($connectedApp)) {
            $this->connectedApps->add($connectedApp);
            $connectedApp->setUser($this);
        }

        return $this;
    }

    public function removeConnectedApp(ConnectedApp $connectedApp): self
    {
        $this->connectedApps->removeElement($connectedApp);

        return $this;
    }
}
