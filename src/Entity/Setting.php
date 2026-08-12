<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'settings')]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $property = null;

    #[ORM\Column(length: 50)]
    private ?string $value = null;

    #[ORM\ManyToOne(inversedBy: 'settings')]
    #[ORM\JoinColumn(name: 'app_configuration_id', referencedColumnName: 'id', nullable: true)]
    private ?AppConfiguration $appConfiguration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProperty(): ?string
    {
        return $this->property;
    }

    public function setProperty(string $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getAppConfiguration(): ?AppConfiguration
    {
        return $this->appConfiguration;
    }

    public function setAppConfiguration(?AppConfiguration $appConfiguration): static
    {
        $this->appConfiguration = $appConfiguration;

        return $this;
    }
}
