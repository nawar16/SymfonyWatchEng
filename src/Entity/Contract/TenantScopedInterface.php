<?php

namespace App\Entity\Contract;

use App\Entity\Tenant;

/**
 * Marker contract for tenant-owned entities.
 *
 * Every entity created after Tenant should expose a non-null tenant relation
 * backed by a tenant_id foreign key.
 */
interface TenantScopedInterface
{
    public function getTenant(): ?Tenant;

    public function setTenant(?Tenant $tenant): static;
}
