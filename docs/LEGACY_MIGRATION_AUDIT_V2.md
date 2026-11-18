# Auditoria de migração C# → Laravel/Vue — V2

**Data:** 2026-08-11  
**Tipo:** auditoria e planeamento — **sem implementação**  
**Fontes:** código real em `legacy-csharp/`, `backend/`, `frontend/` + docs recentes (cruzados, não assumidos)  
**Histórico:** `docs/LEGACY_MIGRATION_AUDIT.md` (V1) permanece; **não usar percentagens V1**.

---

## Veredicto executivo

### PODE APAGAR `legacy-csharp/` AGORA?

# **NÃO**

| Critério | Estado |
|----------|--------|
| Dependência **runtime** C# no Laravel/Vue | **0** (confirmado: sem refs a `.cs`/`.cshtml`/EF/`Areas/` no código ativo) |
| Paridade funcional do legado | **Incompleta** |
| Referência necessária a regras não migradas | **Sim** (reports, email/reminders, settings, Google Calendar, feedback, plans…) |

**Estimativa recalculada (ver §26):** ~**70%** do núcleo operacional ERP/ops; ~**58%** da paridade total do legado (incluindo reports, email, integrações).

---

## 1. Validação executada nesta auditoria

| Comando | Resultado |
|---------|-----------|
| `php artisan route:list` | API `v1` completa (auth, CRUD core, public, cash, stock, sales, payables, receivables, commissions preview, …) |
| `php artisan test` | **122 passed** (629 assertions), 0 falhas |
| `npm run build` | **OK** (exit 0) |
| Grep runtime `legacy-csharp` / `.cshtml` em `backend/app` + `frontend/src` | **0 hits** |

---

## 2. Mapa do sistema novo por área

### 2.1 AUTH

| Item | Estado |
|------|--------|
| Rotas Vue | `/login`, `/register` (`shell=auth`) |
| Endpoints | `POST register/login`, `GET me`, `PATCH profile`, `PUT password`, `POST logout` |
| Controllers / services | `AuthController`; Sanctum |
| Testes | `AuthProfileTest` (8) + registo em `PublicApiTest` |
| Estado | **PARCIAL** — falta forgot/reset, activation/email verify |

### 2.2 ADMIN

| Item | Estado |
|------|--------|
| Layout | `AdminLayout` + `AdminShell` + `admin.css` isolado |
| Conta | `/panel/account` (`AccountView`) |
| Módulos UI | Ver §3 — todos os links da sidebar têm view real |
| Estado | **Núcleo OPERACIONAL migrado**; falta Reports, Settings, fecho de Commissions |

### 2.3 CLIENT

| Item | Estado |
|------|--------|
| Rotas | dashboard, appointments, account |
| API | dashboard client (`next_appointment`, contagens), appointments scoped, notifications, cancel, auth profile |
| Docs | `CLIENT_PANEL_REDESIGN.md` |
| Estado | **MIGRADO** no essencial; GAPs documentados (calendar UI, stats, PDF, reagendar, avaliar, Google) |

### 2.4 PUBLIC

| Item | Estado |
|------|--------|
| Páginas | Home (carousel Vue), services, products, professionals, booking, about, academy, prices, contact, recruitment |
| API pública | catalog + booking + contact |
| Assets | Bootstrap + FA + `style.css` via `publicAssets` (**visual**, não C#) |
| Estado | **MIGRADO** funcionalmente; CSS legado ainda temporário |

### 2.5 FUTURE PROFESSIONAL APP

| Item | Estado |
|------|--------|
| App | Não existe |
| API | Login + appointments + schedules + services + profile **parciais** para app dedicado |
| Estado | **READINESS PARCIAL** — ver §12 |

---

## 3. Admin — matriz de módulos

| Módulo | Vue | API | Testes | Estado |
|--------|-----|-----|--------|--------|
| Dashboard | `DashboardView` | `GET /dashboard` | indireto | **IMPLEMENTADO** |
| Profissionais | `ProfessionalsView` | CRUD + media | `ProfessionalCrudTest` | **IMPLEMENTADO** |
| Serviços do profissional | UI em Professionals | sync `professional/{id}/services` | `ProfessionalServiceAssignmentTest` | **IMPLEMENTADO** |
| Agendamentos | `AppointmentsView` | CRUD + cancel | booking tests | **IMPLEMENTADO** |
| Horários | `ProfessionalSchedulesView` | CRUD schedules | `ProfessionalSchedule*Test` | **IMPLEMENTADO** |
| Clientes | `ClientsView` | CRUD | — | **IMPLEMENTADO** |
| Categorias | `CategoriesView` | service + product categories | `CategoryCrudTest` | **IMPLEMENTADO** |
| Serviços | `ServicesView` | CRUD | — | **IMPLEMENTADO** |
| Produtos | `ProductsView` | CRUD + supplier | stock/sale tests | **IMPLEMENTADO** |
| Fornecedores | `SuppliersView` | CRUD | `SupplierCrudTest` | **IMPLEMENTADO** |
| Stock | `StockView` | entry/exit/adjust/history | `ProductStock*Test` | **IMPLEMENTADO** |
| Vendas | `SalesView` | create/list/cancel | `ProductStockSaleTest` | **IMPLEMENTADO** |
| Caixa | `CashView` | open/close/income/expense/report | cash tests | **IMPLEMENTADO** |
| Contas a pagar | `PayablesView` | CRUD + pay | `PayableCrudTest` | **IMPLEMENTADO** |
| Contas a receber | `ReceivablesView` | CRUD + receive | `ReceivableCrudTest` | **IMPLEMENTADO** |
| **Comissões** | `CommissionsView` | `GET /commissions` preview | `CommissionTest` | **PARCIAL** (não placeholder; sem payable auto / pagamento dedicado) |
| Mensagens | `ContactMessagesView` | list + mark read | public contact | **IMPLEMENTADO** |
| Minha conta | `AccountView` | profile + password | `AuthProfileTest` | **IMPLEMENTADO** |
| Relatórios | — | — | — | **NÃO IMPLEMENTADO** |
| Settings | — | — | — | **NÃO IMPLEMENTADO** |

**Nota:** “Comissões” na sidebar **não** é link morto — há UI + cálculo + testes. Falta fecho operacional (gerar/pagar comissões como fluxo).

---

## 4. Commissions — detalhe

| Parte | Legado | Laravel/Vue | Classificação |
|-------|--------|-------------|---------------|
| Comissão global profissional | `Professional.CommissionPercentage` / `DefaultCommission` | `professionals.commission_percentage` | ✅ |
| Comissão por serviço (pivot) | `ProfessionalService.Commission` | `professional_service.commission_percentage` | ✅ |
| Comissão no Service | `Service.Commission` | **Ausente** (legado de compat) | ⚪ / ❓ |
| Cálculo | parcial / manual | `CommissionService` + `GET /commissions` | 🟡 |
| UI admin | Reports + payables manuais | `CommissionsView` preview | 🟡 |
| Payable automático | **Não** (manual type Commission) | **Não** | ❌ (alinhado ao legado real) |
| Pagamento | Payable pay | `POST payables/{id}/pay` genérico | 🟡 |
| Relatório histórico | `CommissionsReport` + Excel/PDF | Preview apenas | ❌ |
| Base monetária | `Service.Price` | `coalesce(total_price, service.price)` | 🔵 (melhor) |

---

## 5. Reports — legado vs atual

| Relatório | Objetivo | Filtros típicos | Excel | PDF | Laravel | Prioridade |
|-----------|----------|-----------------|-------|-----|---------|------------|
| Sales / SalesReport | Vendas / receita | datas | Sim | Sim | Parcial via Sales+Cash UI | **ALTA** |
| Commissions | Totais comissão | datas, profissional | Sim | Sim | Preview API only | **ALTA** |
| Appointments | Agenda agregada | datas | ? | ? | Admin list only | MÉDIA |
| CashFlow | Fluxo caixa | datas | Sim | Sim | `cash/report` parcial | MÉDIA |
| FinancialSummary | Resumo | datas | Sim | Sim | ❌ | MÉDIA |
| MonthlyTrialBalance | Balancete | mês | ? | ? | ❌ | BAIXA |
| NetProfit | Lucro | período | ? | ? | ❌ | BAIXA |
| ExcelExportService / PdfExportService | Infra export | — | — | — | ❌ | ALTA (infra) |

Views órfãs no legado (actions inconsistentes) → não migrar 1:1; redesenhar a partir de dados Laravel.

---

## 6. Settings

Legado (`Setting` + `AdminSettingsController`): nome clínica, contactos, morada, logos, Instagram, tipo comissão, comissões default extensão/design, pasta imagens, horário comercial, duração default.

| Tipo | Exemplos | Laravel | Ação |
|------|----------|---------|------|
| Essenciais | nome, contactos, timezone, horário | Hardcoded / env parcial | Migrar (settings table ou config) |
| Cosméticas | logos, report logo | Public images estáticos | Parcial / media |
| Integrações | Google tokens (se existirem fora) | ❌ | Com Google Calendar |
| Obsoletas | paths físicos wwwroot | N/A | Descartar |

---

## 7. Auth avançada

| Funcionalidade | Legado | Laravel | Prioridade |
|----------------|--------|---------|------------|
| Login / logout | Sim | ✅ | — |
| Register client | Sim | ✅ (`role=client`) | — |
| Profile / password autenticado | Sim | ✅ | — |
| Forgot password | Sim | ❌ | **ALTA** |
| Reset token + email | Sim | ❌ (tabela `password_reset_tokens` existe sem uso) | **ALTA** |
| Activate account / email activation | Sim | ❌ | MÉDIA |
| Email verification contínua | Parcial | ❌ | MÉDIA |
| Social login Google/Facebook | Sim | ❌ | ⚪ Descartável (salvo decisão negócio) |
| DiagnoseAdmin | Sim | ❌ | ⚪ Descartável |

---

## 8. Notificações / emails

| Capacidade | Legado | Laravel | Estado |
|------------|--------|---------|--------|
| Notificação interna DB | Sim | ✅ model + API | ✅ |
| UI client/admin unread | Parcial | Client dashboard + counts | 🟡 |
| Email booking confirm | `NotificationService` | ❌ | ❌ |
| Email cancel / reschedule | Sim | ❌ | ❌ |
| SignalR realtime | Sim | ❌ | ⚪ / futuro |
| Tawk webhook | Sim | ❌ | ⚪ |
| Mail classes / Jobs | Sim | **0** `app/Mail`, **0** `app/Jobs` | ❌ |

---

## 9. Reminders

| Regra legado | Detalhe | Laravel |
|--------------|---------|---------|
| 24h antes | `AppointmentReminderService` background | ❌ job |
| 2h antes | idem | ❌ job |
| Marca enviado | `Appointment.ReminderSent` | Campo `reminder_sent` **existe**, sem worker |
| Email | SMTP | ❌ |

**Antes de Google Calendar:** definir estratégia (Laravel scheduler + mail) para não misturar reminders com sync Google.

---

## 10. Google Calendar (só auditoria)

Legado: `GoogleCalendarSyncService` (create/update/delete, sync from Google, timezone `Europe/Lisbon`), `PublicApiCalendarController` (webhook/sync/status; event endpoint incompleto).

Laravel: **nenhuma** integração; appointments **sem** `google_event_id` / `google_calendar_id` / `google_sync_status` / `google_synced_at`.

Direção desejada (regra arquitetural):

```
Client/Admin/App → Laravel appointments (SoT) → Google
Google webhook/sync → Laravel appointments → Clients
```

---

## 11. Appointments como fonte de verdade

Schema atual (`appointments`):

`client_id`, `service_id`, `professional_id`, `start_time`, `end_time`, `status`, `notes`, `total_price`, `is_active`, `reminder_sent`, timestamps.

**Suporta** o núcleo SoT.  
**Falta para Google (documentar, não migrar agora):**

| Campo futuro sugerido | Uso |
|-----------------------|-----|
| `google_event_id` | ID evento remoto |
| `google_calendar_id` | Calendar alvo |
| `google_sync_status` | pending/synced/error |
| `google_synced_at` | último sync |
| (opcional) `google_etag` / `last_error` | conflito/diagnóstico |

Timezone: definir oficialmente (legado usava `Europe/Lisbon`).

---

## 12. Professional App — API readiness

| Capacidade app | Endpoints atuais | Estado |
|----------------|------------------|--------|
| Login / me / profile / password | auth/* | **PRONTO** |
| Ver agendamentos (scoped professional) | `GET /appointments` | **PRONTO** |
| Atualizar estado (confirm/complete) | `PUT appointments/{id}` + policy | **PARCIAL** (UI app inexistente; API ok) |
| Horários | `GET professional-schedules` | **PRONTO** (write admin-only) |
| Serviços do profissional | `GET professionals/{id}/services` | **PRONTO** |
| Notificações | notifications API | **PRONTO** |
| Dashboard “meu dia” | dashboard professional block | **PARCIAL** |
| Push / offline | — | **AUSENTE** |
| Endpoints mobile dedicados | — | **AUSENTE** |
| UI app | — | **AUSENTE** |

**Não** deve importar CSS web / AdminLayout / publicAssets.

---

## 13. Client panel (pós-redesign)

| Feature | Estado |
|---------|--------|
| Dashboard + next + completed + notifications | ✅ |
| My appointments + cancel | ✅ |
| Profile + password | ✅ |
| FullCalendar / stats / PDF / reagendar / avaliar / Google | **GAP** — **não** bloqueiam remoção do legado se descartados oficialmente ou adiados |

**Antes de apagar legado:** decidir oficialmente descarte ou migração de avaliar + reagendar + PDF histórico.

---

## 14. Public

Funcional: ✅  
Visual legado ativo: Bootstrap + Font Awesome + `/css/style.css` (temporário).  
jQuery/Slick: **removidos** (`HOME_CAROUSEL_VUE_MIGRATION.md`).  
**Não confundir** CSS legado com dependência C#.

---

## 15. Sales / Cash / Stock

| Domínio | Laravel | Estado |
|---------|---------|--------|
| Sales + SaleItems | ✅ | ✅ MIGRADO |
| Cash register + transactions | ✅ | ✅ |
| Stock movements (entry/exit/adjust) | ✅ (modelo unificado vs Entry/Exit separados) | 🔵 SUBSTITUÍDO |
| Ligação a comissões | Preview sobre appointments completed | 🟡 |
| Ligação a payables/receivables | Manual / CRUD | ✅ parcial vs legado |

---

## 16. ProcedureHistory

Histórico técnico (cliente/serviço/profissional, material, notas, preço).  
Laravel: **ausente**.  
Classificação: **❓ DECISÃO** — útil para ficha clínica/estética; não bloqueia Google; migrar **depois** do núcleo se negócio exigir.

---

## 17. Feedback / Reviews

| Item | Legado | Laravel | Prioridade |
|------|--------|---------|------------|
| Feedback por appointment (1–5) | `PublicFeedbackController` | ❌ | **DEPOIS** Google (ou paralela baixa) |
| ServiceReview genérico | Entidade, pouco uso | ❌ | ❓ / ⚪ |

---

## 18. Plans / PlanAppointment

Pacotes com sessões (`IsSessionUsed`). Sem fluxo controller forte.  
**❓ DECISÃO** — não assumir SaaS subscription; provavelmente pacotes de sessões. Adiar.

---

## 19. Candidatos a descarte (atualizados)

| Item | Útil? | Substituído? | Ação |
|------|-------|--------------|------|
| PriceTable | Baixa | Preços via Services + `/prices` | ⚪ DESCARTÁVEL |
| PaymentMethod **entity** | — | Enum `PaymentMethod` | 🔵 SUBSTITUÍDO |
| Schedule / Shedule | Duplicata | `professional_schedules` | ⚪ |
| Profile entity | Obsoleto | User + Client/Professional | ⚪ |
| Billing / BillingDetails | Fraco uso | Payables/Receivables/Sales | ⚪ / ❓ |
| BeautyCenter | Multi-loja? | Single-tenant atual | ⚪ |
| AccessLog | Audit | — | ⚪ ou futuro audit Laravel |
| Social login | Opcional | — | ⚪ salvo marketing |
| Tawk | Chat 3rd party | — | ⚪ config externa |
| DiagnoseAdmin | Debug | — | ⚪ |
| PDV JS legado | — | Sales + Cash Vue | 🔵 |
| Service.Commission | Compat | pivot + professional % | ⚪ |

---

## 20. Banco — entidades legado ↔ Laravel

| LEGADO | LARAVEL | ESTADO | AÇÃO |
|--------|---------|--------|------|
| User | users | ✅ | — |
| Customer | clients | ✅ | — |
| Professional | professionals | ✅ | — |
| ProfessionalService | professional_service | ✅ | — |
| ProfessionalSchedule | professional_schedules | ✅ | — |
| Schedule/Shedule | — | ⚪ | Descartar |
| Service | services | ✅ | — |
| Category / ProductCategory | service_categories / product_categories | ✅ | — |
| Appointment | appointments | ✅ | Estender p/ Google depois |
| Reminder | reminder_sent + jobs futuros | 🟡 | Jobs |
| Notification | notifications | ✅ | Emails separados |
| Product | products | ✅ | — |
| StockEntry/Exit/Movement | stock_movements | 🔵 | — |
| Sale / SaleItem | sales / sale_items | ✅ | — |
| CashRegister / CashMovement | cash_registers / cash_transactions | ✅ | — |
| Payable | payables | ✅ | — |
| Receivable | receivables | ✅ | — |
| Supplier | suppliers | ✅ | — |
| PaymentMethod entity | enum | 🔵 | — |
| Setting | — | ❌ | Migrar |
| Feedback / ServiceReview | — | ❌ | Decidir |
| ProcedureHistory | — | ❌ | Decidir |
| Plan / PlanAppointment | — | ❌ | Decidir |
| PriceTable | — | ⚪ | Descartar |
| Profile / BeautyCenter / Billing* / AccessLog | — | ⚪ | Descartar |
| Google event fields | — | ❌ | Antes sync |

---

## 25. Matriz de migração atualizada

| Módulo | Legado | Laravel/Vue | Estado | Ação |
|--------|--------|-------------|--------|------|
| Auth básica | Sim | Sim | ✅ MIGRADO | Manter |
| Auth avançada (reset/activate) | Sim | Não | ❌ | Implementar |
| Admin shell / layout | Sim | Sim (melhor) | 🔵 | Manter |
| Dashboard admin | Sim | Sim | ✅ | Manter |
| Professionals + services + schedules | Sim | Sim | ✅ | Manter |
| Clients | Sim | Sim | ✅ | Manter |
| Services + categories | Sim | Sim | ✅ | Manter |
| Products + stock + suppliers | Sim | Sim | ✅ | Manter |
| Sales + cash | Sim | Sim | ✅ | Manter |
| Appointments admin/public | Sim | Sim | ✅ | Manter |
| Payables / Receivables | Sim | Sim | ✅ | Manter |
| Commissions | Sim | Preview | 🟡 PARCIAL | Fechar fluxo |
| Reports + export | Sim | Não | ❌ | Prioridade alta |
| Settings | Sim | Não | ❌ | Prioridade média-alta |
| Contact messages | Sim | Sim | ✅ | Manter |
| Notifications DB | Sim | Sim | ✅ | Manter |
| Emails / reminders jobs | Sim | Campo only | 🟡/❌ | Antes Google |
| Client panel | Sim | Sim (redesign) | ✅ / 🟡 gaps | Decidir gaps |
| Public site | Sim | Sim | ✅ | Migrar CSS depois |
| Google Calendar | Stub/parcial | Não | ❌ | Após pré-requisitos |
| Feedback | Sim | Não | ❌ | Depois Google |
| ProcedureHistory | Sim | Não | ❓ | Decisão |
| Plans | Fraco | Não | ❓ | Decisão |
| PriceTable / social / Tawk / PDV JS | Sim | N/A | ⚪ | Descartar oficial |
| Professional mobile app | N/A | API parcial | ❌ app | Após API gaps |

---

## 26. Percentagem recalculada

### Método

Módulos **em âmbito de negócio** (excluindo ⚪ descartáveis): **40** unidades ponderadas.

| Classe | Qtd | Peso |
|--------|-----|------|
| ✅ MIGRADO / 🔵 SUBSTITUÍDO (feito) | 24 | 1.0 → 24 |
| 🟡 PARCIAL | 6 | 0.5 → 3 |
| ❌ NÃO MIGRADO | 7 | 0 → 0 |
| ❓ DECISÃO (não conta no numerador; conta no denominador a 0.25 até decidir) | 3 | 0.25 → 0.75 |

**Score ≈ (24 + 3 + 0.75) / 40 = 27.75 / 40 ≈ 69%**

| Métrica | Valor |
|---------|------:|
| **Núcleo operacional ERP (admin ops + public booking + client core)** | **~75–80%** |
| **Paridade total legado (com reports/email/Google/feedback)** | **~58–62%** |
| **Usado nesta V2 como referência principal** | **~70%** |

V1 (~45%) está **obsoleto** — desde então entraram Schedules, Suppliers, Payables, Receivables, Commissions preview, Admin shell, Client redesign, isolamento CSS, Home Vue, etc.

---

## 27. Bloqueadores para remover `legacy-csharp/`

### ALTA

| Funcionalidade | Legado | Equivalente | Ação | vs Google |
|----------------|--------|-------------|------|-----------|
| Reports + export | AdminReports + Excel/Pdf | Parcial cash/sales UI | Implementar reports Laravel | **ANTES** (negócio) |
| Commissions fecho | Payables manuais + report | Preview | Gerar/pagar + histórico | **ANTES** ou paralelo |
| Email + reset password | Account/EmailService | — | Auth + mail infra | **ANTES** |
| Reminders 24h/2h | AppointmentReminderService | `reminder_sent` | Scheduler + mail | **ANTES** Google |

### MÉDIA

| Funcionalidade | Ação | vs Google |
|----------------|------|-----------|
| Settings essenciais | Tabela/config | **ANTES** (timezone, contactos) |
| Reagendar appointment | Endpoint + UI | Pode ser **ANTES** |
| Feedback | Migrar ou descarte oficial | **DEPOIS** Google ok |
| Professional API gaps (“meu dia”) | Endpoints lean | **ANTES** app; paralelo Google |

### BAIXA

| Item | Ação |
|------|------|
| ProcedureHistory / Plans | Decisão escrita |
| Client FullCalendar / PDF / stats | Descarte ou fase late |
| CSS Bootstrap público | Migração visual (não bloqueia apagar C#) |
| Descarte formal PriceTable/social/Tawk | Documento + checklist |

---

## 28. Ordem recomendada (a partir de agora)

1. **Commissions** — fechar fluxo (gerar payable opcional + marcar pago + UI)  
2. **Mail infrastructure** + **Forgot/Reset password**  
3. **Reminders** (scheduler 24h/2h usando `reminder_sent`)  
4. **Settings** essenciais (incl. timezone `Europe/Lisbon`)  
5. **Reports** operacionais (Sales, Cash, Commissions) + export PDF/Excel  
6. **Google Calendar readiness** (campos appointments + OAuth + sync service)  
7. **Google Calendar** implementação (SoT = Laravel appointments)  
8. **Feedback** (ou descarte oficial)  
9. **Professional API gaps** + App Profissional  
10. Decisões **ProcedureHistory / Plans**  
11. Descarte oficial candidatos ⚪  
12. Remover `legacy-csharp/` do repositório  

---

## 29. Google Calendar readiness checklist

| Item | Estado |
|------|--------|
| appointments estáveis | **READY** |
| professional schedule estável | **READY** |
| service/professional vínculo estável | **READY** |
| timezone definido | **PARTIAL** (legado Lisbon; settings Laravel ❌) |
| cliente booking estável | **READY** |
| admin appointments estável | **READY** |
| professional endpoints suficientes | **PARTIAL** |
| notifications strategy definida | **PARTIAL** (DB sim; email/push não) |
| email strategy definida | **BLOCKED** (sem Mail/Jobs) |
| conflict strategy definida | **PARTIAL** (conflitos agenda sim; Google etag não) |
| source of truth definida | **READY** (regra: Laravel appointments) |
| campos google_* no schema | **BLOCKED** (ainda não criados — intencional) |

**Conclusão readiness:** **PARTIAL** — não iniciar Google até mail/reminders/settings timezone + desenho de sync.

---

## 30. Dependências de runtime C#

| Tipo | Resultado |
|------|-----------|
| Runtime Laravel/Vue | **0** |
| Build | **0** |
| Docs / pasta `legacy-csharp/` | Referência humana apenas |

---

## 31. Resumo para a resposta ao utilizador

Ver secção seguinte no chat (espelho dos 16 pontos pedidos).

---

*Fim V2. V1 permanece em `docs/LEGACY_MIGRATION_AUDIT.md`.*
