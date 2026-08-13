# Workflow: Płatności i raty

## 1. Sekwencja (rodzic / uczestnik)

```
Harmonogram / Moje wpłaty / link e-mail / /rodzic/{token}
  → CTA „Zapłać online”
  → (opcjonalnie) strona signed z danymi przelewu
  → checkout (fake lub bramka)
  → online-success
```

## 2. Sekwencja (biuro)

```
Rejestr wpłat / Skrzynka płatności / Impreza → Finanse → Wpłaty
  → księgowanie ręczne lub import bankowy
  → deep-link do imprezy przy potrzebie korekty
```

## 3. Automatyzacje

- Przypomnienia: `SendPaymentRemindersCommand` + mail szablonowy
- Sync salda: `ParticipantPaymentBalanceService` / ledger
