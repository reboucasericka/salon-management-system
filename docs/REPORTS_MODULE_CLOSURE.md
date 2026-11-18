# REPORTS — Fecho do módulo (2026-08-21)

## REPORTS FECHADO?

**SIM** — para os relatórios operacionais confirmados no legado com dados/cálculo reais.

## GOOGLE CALENDAR READINESS

**PARTIAL** — appointments + timezone Settings prontos; OAuth/google_event_id/sync **não** iniciados.

---

## 1. Entities relevantes (`legacy-csharp/Data/Entities`)

37 ficheiros. Principais:

| Entity | Tabela | Equivalente Laravel | Estado |
|---|---|---|---|
| Appointment | Appointments | appointments | MIGRADO |
| Professional | Professionals | professionals | MIGRADO |
| ProfessionalService | ProfessionalServices | professional_service | MIGRADO |
| ProfessionalSchedule | ProfessionalSchedules | professional_schedules | MIGRADO |
| Service | Services | services | MIGRADO |
| Category | Categories | service_categories / product_categories | SUBSTITUÍDO |
| Product | Products | products | MIGRADO |
| ProductCategory | ProductCategories | product_categories | MIGRADO |
| Supplier | Suppliers | suppliers | MIGRADO |
| Sale / SaleItem | Sales / SaleItems | sales / sale_items | MIGRADO (sem professional_id) |
| CashRegister / CashMovement | CashRegisters / CashMovements | cash_registers / cash_transactions | MIGRADO |
| Payable / Receivable | Payables / Receivables | payables / receivables | MIGRADO |
| Customer | Customers | clients | SUBSTITUÍDO |
| Setting | Settings | settings | MIGRADO (singleton) |
| Reminder | Reminder | appointment_reminders | SUBSTITUÍDO |
| Billing / BillingDetails | — | — | DESCARTADO (não no Laravel) |
| Plan / PlanAppointment | — | — | DESCARTADO |
| ProcedureHistory | — | — | DESCARTADO |
| PaymentMethod (entity) | — | enum payment_method | SUBSTITUÍDO |
| PriceTable / BeautyCenter | — | — | DESCARTADO |
| AccessLog / Profile / Feedback / ServiceReview | — | — | DESCARTADO / AUSENTE |
| StockEntry / StockExit / StockMovement | — | stock_movements | PARCIAL |
| Shedule | — | — | DESCARTADO (typo legado) |
| Notification | — | notifications (custom) | PARCIAL |
| User | Users | users | MIGRADO |

## 2. Relações reconstruídas (reporting)

```
Professional 1—N Appointment (professional_id)
Service 1—N Appointment (service_id)
Client/Customer 1—N Appointment (client_id)
Professional N—N Service via ProfessionalService
Sale 1—N SaleItem
Client 1—N Sale
Sale 1—N Receivable (sale_id opcional)
Payable N—1 Professional / Supplier / Appointment
CashRegister 1—N CashTransaction
Product N—1 Supplier
Product 1—N StockMovement
```

Relações mortas para reports: Billing→*, Plan→*, Reminder tabela, BeautyCenter.

## 3. Reports reais no legado

| Report | Controller | View | Estado legado |
|---|---|---|---|
| Index/Overview | Index + charts | Index.cshtml | Funcional |
| SalesReport | SalesReport | SalesReport.cshtml placeholder | Cálculo OK / UI fraca |
| Sales (Billing) | — | Sales.cshtml | Órfã |
| CashFlowReport | CashFlowReport | sem view | Cálculo OK / view em falta |
| CommissionsReport | CommissionsReport | Commissions.cshtml incompatível | Parcial |
| FinancialSummary | FinancialSummary | sem view | Cálculo OK |
| Appointments | — | Appointments.cshtml | Órfã (só view) |
| NetProfit | — | NetProfit.cshtml | Órfã, **sem fórmula no controller** |
| MonthlyTrialBalance | — | MonthlyTrialBalance.cshtml | Órfã |
| Daily cash | AdminCashRegister | DailyReport | Fora AdminReports |

## 4. Mortos/incompletos

- NetProfit sem backend
- Sales.cshtml (Billing)
- PDF POST = HTML com content-type PDF
- ExportToPDF GET = HTML
- Charts Chart.js no Index (não migrados como charts)
- Views órfãs / actions sem view

## 5. Migrados (Laravel)

1. Overview  
2. Appointments  
3. Sales  
4. Commissions (via CommissionService + payables)  
5. Financial (CashFlow + FinancialSummary)  
6. Cash monthly (MonthlyTrialBalance operacional)

## 6. Descartados

| Report | Motivo |
|---|---|
| NetProfit separado | Sem fórmula no controller |
| Sales/Billing | Entity Billing inexistente no Laravel |
| Stock report dedicado | Fora AdminReports; Stock UI já existe |
| Charts Chart.js | Sem valor obrigatório; UX compacta |

## 7. Fórmulas

**Financial / operational_result (DECISÃO DE MIGRAÇÃO):**  
`sales_paid_amount − payables_paid_amount` no período.

Também expõe (paridade CashFlow/FinancialSummary):
- receivables total/received/pending (por `created_at`)
- payables total/paid/pending + por tipo
- sales total/paid/count
- commissions via payables type=commission
- cash_income / cash_expense / cash_net

**Comissões:** `CommissionService::previewFromCompletedAppointments` (não recalcular legado).

**Caixa mensal:** agrega `CashService::dailyReport` por dia do mês; diferença abertura/fecho = operacional, **não** balancete contabilístico formal.

## 8. Filtros

- `from` / `to` (timezone Settings)
- `professional_id`, `service_id`, `client_id`, `status`
- `payment_method`, `financial_status` (commissions)
- `year` / `month` (cash)

## 9. Timezone

`SettingsService::timezone()`; limites convertidos para UTC na query.

## 10. Currency / branding

`SettingsService::currency()` / `salonName()` nos meta e PDF.

## 11. Exports

- **CSV** (substitui ClosedXML XLSX — paridade de dados sem pacote Excel)
- **PDF** real via `barryvdh/laravel-dompdf` `^3.1`
- Query `?format=csv|pdf`

## 12. Dependências

- `barryvdh/laravel-dompdf` ^3.1

## 13. Endpoints (admin)

```
GET /api/v1/reports/overview
GET /api/v1/reports/appointments
GET /api/v1/reports/sales
GET /api/v1/reports/commissions
GET /api/v1/reports/financial
GET /api/v1/reports/cash
```

## 14. Frontend

`/panel/admin/reports` · sidebar Financeiro → Relatórios · tabs compactas + filtros + CSV/PDF

## 15–16. Ficheiros

**Criados:** ReportService, ReportExportService, ReportController, ReportFilterRequest, views `resources/views/reports/*`, ReportsView.vue, ReportsTest.php, este doc.

**Alterados:** routes/api.php, AdminShell, router, composer.json/lock.

## 17–19. Validação

- Testes reports: 7 passed  
- Suite: **160 passed** (795 assertions)  
- `npm run build`: OK  

## 20. Limitações

- Sem XLSX nativo (CSV)
- Sem gráficos
- Sales sem professional_id (não existe no schema)
- Receivables sem due_date
- Cash monthly itera dias (OK para 31 dias)
- NetProfit formal não existe

## 21. Readiness Google Calendar

PARTIAL — não bloqueado por Reports; falta OAuth/sync/`google_event_id`.
