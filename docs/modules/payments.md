# Moduł: Płatności

## 1. Opis

Ewidencja wpłat zaliczek, harmonogramy rat, linki do szybkich płatności (portal klienta / rodzic / signed URL).

## 2. Macierz wejść UX

| Wejście | Kto | CTA | Flow |
|---|---|---|---|
| Portal klienta → Harmonogram | uczestnik / opiekun | **Zapłać online** | signed installment → checkout |
| Portal klienta → Moje wpłaty | uczestnik | **Zapłać online** | ten sam signed flow |
| Portal klienta → Wpłaty grupy | opiekun | brak (raport) | link do harmonogramu |
| `/rodzic/{token}` | magiczny link | **Zapłać online** | `InitiateOnlinePaymentAction` → checkout |
| Signed `/payments/installment/...` | każdy z podpisem | **Zapłać online** (+ „symulator” przy fake) | → `/pay` → checkout |
| Success | wszyscy | — | wspólny `online-success` |

Label CTA: `App\Support\PaymentCta::label()` — jedna nazwa we wszystkich portalach.

## 3. Admin

| Ekran | Rola |
|---|---|
| Impreza → Finanse → **Wpłaty** | kanon wpłat danej imprezy |
| Finanse → **Rejestr wpłat** (`ParticipantPaymentsPage`) | cross-event → deep-link do imprezy |
| Skrzynka płatności | terminy / zaległe / kopiowanie linków |

## 4. Actions

- `GenerateInstallmentPaymentLinkAction` — signed URL
- `InitiateOnlinePaymentAction` / `CompleteOnlinePaymentAction` — sesja bramki (obecnie fake; produkcyjny gateway osobno)
