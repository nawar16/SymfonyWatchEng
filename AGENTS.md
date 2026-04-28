# Symfony Multi-Tenant Agent
## Project Context
- Framework: Symfony 7 (Skeleton)
- Infrastructure: Docker-first
- Database: PostgreSQL (Shared schema)

## Rules
- Scoped Uniqueness: Users must be unique per tenant (email + tenant_id)
- Contextual Awareness: Always resolve Tenant via Hostname before any Auth or DB action
- Use PHP 8.3 attributes and strict typing

## Security Rules
- No Global Users: Every User MUST implement TenantScopedInterface
- Tenant-Aware Auth: Authenticated users must belong to the active TenantContext
- JWT: Use lexik/jwt-authentication-bundle