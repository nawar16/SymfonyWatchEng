A multi-tenant monitoring engine built by symfony
Each tenant gets a private workspace to track URLs, analyze response times, and receive instant alerts when services fail

## Testing
php bin/phpunit

![CI](https://github.com/nawar16/SymfonyWatchEng/actions/workflows/ci.yml/badge.svg)

## Tenant Identification Process
- Requests resolve the current tenant from the request hostname subdomain.
- 'companya.localhost' resolves tenant subdomain 'companya'.
- 'companyb.yourapp.com' resolves tenant subdomain 'companyb'.

## Local DNS
- For local development, prefer '*.localhost' style hostnames such as 'companya.localhost'.
- If your browser or OS does not resolve wildcard localhost names the way you want, use 'dnsmasq' or add explicit host entries for the tenants you are testing.
- Example local URLs:
  - 'http://companya.localhost:8080'
  - 'http://companyb.localhost:8080'
