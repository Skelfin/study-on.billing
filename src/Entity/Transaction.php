<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{

    public const TYPE_PAYMENT = 1;
    public const TYPE_DEPOSIT = 2;

    private static array $typeNames = [
        self::TYPE_PAYMENT => 'payment',
        self::TYPE_DEPOSIT => 'deposit',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private BillingUser $user;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?Course $course = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private int $type;

    #[ORM\Column]
    private float $amount;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?BillingUser
    {
        return $this->user;
    }

    public function setUser(?BillingUser $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function getTypeName(): string
    {
        return self::$typeNames[$this->type] ?? 'unknown';
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}