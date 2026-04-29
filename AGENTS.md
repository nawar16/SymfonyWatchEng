# Symfony Multi-Tenant Agent
## Project Context
- Framework: Symfony 7 (Skeleton)
- Infrastructure: Docker-first
- Database: PostgreSQL (Shared schema)

## Rules
- Subdomain Resolution: Identify Tenants via Hostname parsing (Host -> Subdomain lookup).
- Database Multi-tenancy: Shared schema using Doctrine SQL Filters on `tenant_id`.
- Scoped Uniqueness: Users must be unique per tenant (Email + TenantID).
- Fail-Fast: 404 error if the subdomain does not exist in the `tenant` table.
- Strict Context: The `TenantContext` service is the single source of truth for the active tenant during a request.

## Security Rules
- No Global Users: Every User MUST implement TenantScopedInterface
- Tenant-Aware Auth: Authenticated users must belong to the active TenantContext
- JWT: Use lexik/jwt-authentication-bundle