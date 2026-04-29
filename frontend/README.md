# WatchEng Frontend

Vue 3 + Vite frontend for the Symfony multi-tenant API.

## Setup

```bash
npm install
npm run dev
```

The development server proxies `/api/*` and `/dashboard` to `VITE_BACKEND_URL`, which defaults to `http://localhost:8080`.

Register uses `POST /api/register`, login uses `POST /api/login_check`, and the dashboard uses `GET /dashboard` with the JWT bearer token returned by Lexik.
