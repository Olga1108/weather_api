<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[UniqueEntity(fields: ['email'], message: 'This email is already subscribed.')]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: Types::INTEGER)]
	private ?int $id = null;

	#[ORM\Column(type: Types::STRING, length: 255, unique: true)]
	#[Assert\NotBlank]
	#[Assert\Email]
	private ?string $email = null;

	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Assert\NotBlank]
	private ?string $city = null;

	#[ORM\Column(type: Types::STRING, length: 50)]
	#[Assert\NotBlank]
	#[Assert\Choice(choices: ['hourly', 'daily'], message: 'Choose a valid frequency: hourly or daily.')]
	private ?string $frequency = null;

	#[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
	private ?string $confirmationToken = null;

	#[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
	private ?string $unsubscribeToken = null;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $isConfirmed = false;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ?\DateTimeImmutable $createdAt = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	private ?\DateTimeInterface $updatedAt = null;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(string $email): static
	{
		$this->email = $email;
		return $this;
	}

	public function getCity(): ?string
	{
		return $this->city;
	}

	public function setCity(string $city): static
	{
		$this->city = $city;
		return $this;
	}

	public function getFrequency(): ?string
	{
		return $this->frequency;
	}

	public function setFrequency(string $frequency): static
	{
		$this->frequency = $frequency;
		return $this;
	}

	public function getConfirmationToken(): ?string
	{
		return $this->confirmationToken;
	}

	public function setConfirmationToken(?string $confirmationToken): static
	{
		$this->confirmationToken = $confirmationToken;
		return $this;
	}

	public function getUnsubscribeToken(): ?string
	{
		return $this->unsubscribeToken;
	}

	public function setUnsubscribeToken(?string $unsubscribeToken): static
	{
		$this->unsubscribeToken = $unsubscribeToken;
		return $this;
	}

	public function isIsConfirmed(): bool
	{
		return $this->isConfirmed;
	}

	public function setIsConfirmed(bool $isConfirmed): static
	{
		$this->isConfirmed = $isConfirmed;
		return $this;
	}

	public function getCreatedAt(): ?\DateTimeImmutable
	{
		return $this->createdAt;
	}

	#[ORM\PrePersist]
	public function setCreatedAtValue(): void
	{
		$this->createdAt = new \DateTimeImmutable();
	}

	public function getUpdatedAt(): ?\DateTimeInterface
	{
		return $this->updatedAt;
	}

	#[ORM\PreUpdate]
	public function setUpdatedAtValue(): void
	{
		$this->updatedAt = new \DateTime();
	}
}
