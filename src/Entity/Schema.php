<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

use ICal\Event;

class Schema
{
    #[Assert\NotBlank]
    private ?string $property = null;

    /**
     * @var Collection<int, Comment>
     */
    private Collection $fields;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
    }

    public function getProperty(): ?string
    {
        return $this->property;
    }

    public function setProperty(string $property): self
    {
        if (property_exists(Event::class, $property)) {
          $this->property = $property;
        } else {
          throw new \InvalidArgumentException(
              sprintf("Property '%s' does not exist in class %s.", $property, Event::class)
          );
        }
        return $this;
    }

    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function setFields(array $fields): self
    {
        $this->fields = new ArrayCollection($fields);
        return $this;
    }

    public function addField(int $key, string $field): self
    {
        $this->fields->set($key, $field);
        return $this;
    }

    public function removeField(int $key): self
    {
        $this->fields->remove($key);
        return $this;
    }
}
