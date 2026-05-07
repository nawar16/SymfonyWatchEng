<?php

namespace App\Shared\Domain;

use App\Entity\Tenant;

interface TenantScopedInterface
{
    public function getTenant(): ?Tenant;

    public function setTenant(?Tenant $tenant): static;
}
