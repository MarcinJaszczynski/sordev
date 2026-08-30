# TourERP / BusVibe Documentation Index

Witaj w głównym indeksie dokumentacji systemu **TourERP / BusVibe Core**. 
Dokumentacja pełni rolę "Konstytucji Projektu" dla programistów oraz agentów AI (Cursor, Claude Code, Roo Code, GitHub Copilot).

## Master File Structure

```
/docs
├── 00_PROJECT_VISION.md
├── 01_PRODUCT_GUIDELINES.md
├── 02_UX_GUIDELINES.md
├── 03_UI_DESIGN_SYSTEM.md
├── 04_LARAVEL_STANDARDS.md
├── 05_FILAMENT_STANDARDS.md
├── 06_DATABASE_STANDARDS.md
├── 07_PERFORMANCE.md
├── 08_SECURITY_AND_GDPR.md
├── 09_AI_RULES.md
├── 10_API.md
│
├── (zrzuty / backup MySQL → patrz [`database/dumps/README.md`](../database/dumps/README.md))
│
├── api/
│   ├── README.md
│   ├── openapi-public.yaml
│   ├── openapi-pilot.yaml
│   └── openapi-client.yaml
│
├── modules/
│   ├── dashboard.md
│   ├── trips.md
│   ├── itinerary.md
│   ├── itinerary_points.md
│   ├── participants.md
│   ├── schools_and_clients.md
│   ├── pilots.md
│   ├── buses.md
│   ├── hotels.md
│   ├── payments.md
│   ├── expenses.md
│   ├── settlements.md
│   ├── invoices.md
│   ├── ksef.md
│   ├── crm.md
│   ├── calendar.md
│   └── reports.md
│
├── workflows/
│   ├── office_path.md
│   ├── permissions_matrix.md
│   ├── reservation.md
│   ├── payment.md
│   ├── settlement.md
│   ├── trip_execution.md
│   └── invoice.md
│
├── checklists/
│   ├── ux.md
│   ├── performance.md
│   ├── code_review.md
│   ├── testing.md
│   └── release.md
│
└── .cursor/
    └── rules/
        └── tour-erp-core.mdrules
```
