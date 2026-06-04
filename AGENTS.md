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

## Scheduler & Concurrency Rules
- **Keep it Separated**: Don't mix database logic with schedule logic. The scheduler is just a simple alarm clock that wakes up the system every 1 minute with a `TriggerMonitorChecksCommand`.
- **The Orchestrator Runs the Loop**: The `TriggerMonitorChecksHandler` finds ready monitors, handles locks, updates timestamps, and trigger `CheckMonitorCommand` worker jobs.
- **Locking Prevents Double Checking**: Always use a 55-second Redis lock (`SharedLockInterface`) per monitor ID, to stop multiple app servers from checking the exact same monitor at the same time.
- **Never flush inside loops**: Handel all the updates in memory and run a single `$entityManager->flush()` at the end to keep database transactions fast.
- **Testing Note**: Symfony's concrete `Lock` class is `final`. When writing unit tests for the handler, you have to mock `SharedLockInterface` instead, to not crash PHPUnit.

## Incident Escalation & Notification Rules
- **Unified Event Pipeline**: All alerting, alerting rules, and escalations must go through the `NotificationHandler` reacting asynchronously to `IncidentCreatedEvent` and `IncidentResolvedEvent`.
- **Timer-Driven Escalation**: Escalation timelines must use deferred queue tasks via `CheckEscalationCommand` loaded with Symfony Messenger's `DelayStamp`.
- **Zero-Delay Uniformity Rule**: The `NotificationHandler` always dispatches a `CheckEscalationCommand` for step index `0` immediately upon incident creation. Initial notification rules (delays, business hour) are evaluated inside the timeline engine rather than separating entry points.
- **Circuit-Breaker Safety**: The `CheckEscalationHandler` must check the incident lifecycle state dynamically upon payload consumption. If the target incident is missing or transitions to `RESOLVED`, the execution loop must exit immediately to kill further notifications.