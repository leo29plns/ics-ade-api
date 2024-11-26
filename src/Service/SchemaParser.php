<?php

namespace App\Service;

use App\Entity\Schema;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ICal\Event;

class SchemaParser
{
    private Collection $schemas;

    public function __construct()
    {
        $this->schemas = new ArrayCollection();
    }

    public function decodeSchema(array $data): Schema
    {
        $schema = new Schema();

        $schema->setProperty($data['property'])
            ->setFields($data['fields']);

        return $schema;
    }

    public function decodeSchemas(array $schemas): void
    {
        foreach ($schemas as $schema) {
            $this->schemas->add($this->decodeSchema($schema));
        }
    }

    public function getSchemas(): Collection
    {
        return $this->schemas;
    }

    private function searchField(string $propertyValue, string $pattern, string $type = 'string', string $arrayType = 'string'): mixed
    {
        if (!preg_match_all($pattern, $propertyValue, $matches)) {
            return null;
        }

        if (str_starts_with($pattern, '#')) {
            dd($matches);
        }
    
        if ($type === 'array' && isset($matches[1])) {
            $value = $matches[1];
    
            $validatedArray = array_map(function ($item) use ($arrayType) {
                return self::convertToTypeIfValid($item, $arrayType);
            }, $value);
    
            $validatedArray = array_filter($validatedArray, fn($item) => $item !== null);
    
            return !empty($validatedArray) ? array_values($validatedArray) : null;
        }
    
        $value = $matches[0][0] ?? null;
    
        return self::convertToTypeIfValid($value, $type);
    }
    
    private static function convertToTypeIfValid(string $value, string $type): mixed
    {
        return match ($type) {
            'string' => $value,
            'int', 'integer' => ctype_digit($value) ? (int)$value : null,
            'float', 'double' => is_numeric($value) ? (float)$value : null,
            'bool', 'boolean' => ($bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) !== null
                ? $bool
                : null,
            default => null,
        };
    }    

    public function searchSchema(Event $event, Schema $schema): array
    {
        $property = $schema->getProperty();
        $propertyValue = $event->$property;

        $result = [];
        if ($propertyValue) {
            foreach ($schema->getFields() as $field) {
                $fieldName = $field['name'];
                $result[$fieldName] = $this->searchField(
                    propertyValue: $propertyValue,
                    pattern: $field['pattern'],
                    type: $field['type'] ?? 'string',
                    arrayType: $field['arrayType'] ?? 'string'
                );
            }
        }

        return $result;
    }

    public function searchSchemas(Event $event): array
    {
        $result = [];
        foreach ($this->schemas as $schema) {
            $property = $schema->getProperty();
            $result[$property] = $this->searchSchema($event, $schema);
        }

        return $result;
    }
}
