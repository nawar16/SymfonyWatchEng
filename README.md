A multi-tenant monitoring platform built by symfony 

Each tenant has an isolated workspace to monitor HTTP endpoints, track response times, and receive notifications when monitored services become unavailable. The project focuses on building a scalable distributed monitoring engine using asynchronous processing and tenant isolation.

## Testing
php bin/phpunit

![CI](https://github.com/nawar16/SymfonyWatchEng/actions/workflows/ci.yml/badge.svg)

## Features
- Multi-tenant architecture with subdomain-based tenant resolution
- Tenant isolation using Doctrine Filters
- Monitor management with configurable check frequency
- Distributed heartbeat engine powered by Symfony Messenger and Redis
- Distributed scheduling protected with Symfony Lock to prevent duplicate monitor execution
- Incident detection based on consecutive failures
- Redis-backed real-time status snapshots
- Docker development environment (PHP, MySQL, Redis, Mailpit)
- PHPUnit test suite with GitHub Actions CI

## Tenant Identification Process
- Requests resolve the current tenant from the request hostname subdomain.
- 'companya.localhost' resolves tenant subdomain 'companya'.
- 'companyb.yourapp.com' resolves tenant subdomain 'companyb'.

## Local DNS
- For local development, prefer '*.localhost' style hostnames such as 'companya.localhost'.
- If your browser or OS does not resolve wildcard localhost names, use 'dnsmasq' or add explicit host entries for the tenants you are testing.
- Example local URLs:
  - 'http://companya.localhost:8080'
  - 'http://companyb.localhost:8080'
