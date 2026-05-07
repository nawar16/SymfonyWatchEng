<?php

namespace App\Tenancy\Application;

use App\Entity\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function getCurrentTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getTenant(): ?Tenant
    {
        return $this->getCurrentTenant();
    }

    public function setCurrentTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function setTenant(?Tenant $tenant): void
    {
        $this->setCurrentTenant($tenant);
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }
}
