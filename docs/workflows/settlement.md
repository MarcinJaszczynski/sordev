# Workflow: Rozliczanie imprezy

## 1. Sekwencja

```
Status „Do rozliczenia”
  → Impreza → Finanse (Koszty)
  → Uzupełnij plan / wpłaty kosztowe / dokumenty
  → Wpłaty uczestników (nested)
  → Gotówka pilota
  → Status „Rozliczona”
```

## 2. Gdzie klikać

| Krok | UI |
|---|---|
| Koszty i semafor | **Finanse → Koszty** |
| Wpłaty uczestników | **Finanse → Wpłaty** |
| Zaliczka / waluty | **Finanse → Gotówka i waluty** |
| Pulpit cross-event | **Pulpit finansowy** |

Legacy URL (`…/settlement`, `…/event-settlements`) → redirect do powyższego kanonu.
