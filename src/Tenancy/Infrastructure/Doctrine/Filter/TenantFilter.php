<?php

namespace App\Tenancy\Infrastructure\Doctrine\Filter;

use App\Shared\Domain\TenantScopedInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $reflectionClass = $targetEntity->getReflectionClass();

        if ($reflectionClass === null || !$reflectionClass->implementsInterface(TenantScopedInterface::class)) {
            return '';
        }

        if (!$targetEntity->hasAssociation('tenant')) {
            return '';
        }

        $association = $targetEntity->getAssociationMapping('tenant');
        $joinColumns = $association['joinColumns'] ?? [];
        $tenantColumn = $joinColumns[0]['name'] ?? 'tenant_id';

        return sprintf('%s.%s = %s', $targetTableAlias, $tenantColumn, $this->getParameter('tenant_id'));
    }
}
