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

## DDD Architectural Rules
- **Domain Isolation**: Business logic and Entities must reside in `src/[Context]/Domain`. No framework dependencies (like Controllers) are allowed here.
- **Infrastructure Layer**: All Symfony-specific implementations (Listeners, Filters, Repositories) must reside in `src/[Context]/Infrastructure`.
- **Entity Mapping**: Use Doctrine Attribute mapping explicitly defined in `doctrine.yaml`.   
- **Bounded Contexts**: 
    - `Tenancy`: Handles the SaaS shell, subdomains, and tenant lifecycle.
    - `Identity`: Handles authentication, roles, and user profiles.
    - `Monitoring`: Handles pings, health checks, and incidents.
- **Cross-Context Communication**: Use the `Shared` folder for interfaces or Dispatch Events. Never allow `Identity` entities to have direct dependencies on `Monitoring` logic.

## Redis Rules

- Redis handles purely ephemeral state data. Dashboard queries fetch status snapshots from Redis keys directly instead of executing expensive SQL aggregations over millions of logs.

- Redis Key Schemas
1. **Current Status**
   * **Key**: `monitor:{id}:status`
   * **Type**: `json string`
   * **Payload**: `{"status": "UP|DOWN", "response_time": int, "checked_at": "ATOM_string", "status_code": int}`

2. **Failure Accumulator**
   * **Key**: `monitor:{id}:failures`
   * **Type**: `integer`
   * **Use Case**: Atomic increments (`INCR`) to cleanly establish consecutive drops before spawning incident alerts.

3. **Active Incident Cache**
   * **Key**: `monitor:{id}:incident`
   * **Type**: `json string`
   * **Use Case**: Quick lookup dashboard tags (`{"incident_id": int, "started_at": "string"}`) to avoid cross-table MySQL joins.
