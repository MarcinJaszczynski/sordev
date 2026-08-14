# API docs (OpenAPI 3)

Kontrakty mobilne TourERP / BusVibe.

| Plik | Audience | Base path |
|------|----------|-----------|
| [openapi-public.yaml](./openapi-public.yaml) | storefront | `/api/v1/public` |
| [openapi-pilot.yaml](./openapi-pilot.yaml) | pilot app | `/api/v1/pilot` |
| [openapi-client.yaml](./openapi-client.yaml) | client app | `/api/v1/client` |

Konstytucja: [`../10_API.md`](../10_API.md).

Auth (pilot/client): Sanctum Bearer z ability `pilot:read` / `client:read`.
Public: bez auth, rate limit 60/min.
