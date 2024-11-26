<?php

namespace App\Repository;

use App\Entity\Schema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use ICal\Event;

class SchemaRepository extends ServiceEntityRepository
{
    private Collection $schemas;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schema::class);
        $this->schemas = new ArrayCollection();
    }

    public function searchSchema(Event $event, string $pattern, array $fields, string $property): array
    {
        $propertyValue = $event->__get($property);
        $result = [];

        if ($propertyValue && preg_match_all($pattern, $propertyValue, $matches)) {
            foreach ($fields as $index => $field) {
                $result[$field] = isset($matches[$index + 1]) ? $matches[$index + 1][0] : null;
            }
        }

        return $result;
    }

    public function decodeSchema(array $data): Schema
    {
        $schema = new Schema();
        $schema->setProperty($data['property'])
            ->setPattern($data['pattern'])
            ->setFields($data['fields']);

        return $schema;
    }

    public function decodeSchemas(array $schemasData): void
    {
        foreach ($schemasData as $schemaData) {
            $this->schemas->add($this->decodeSchema($schemaData));
        }
    }

    public function getSchemas(): Collection
    {
        return $this->schemas;
    }

    public function findSchemaById(int $id): ?Schema
    {
        return $this->find($id);
    }

    public function findAllSchemas(): array
    {
        return $this->findAll();
    }
}
