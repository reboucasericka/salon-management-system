# Auditoria e redesign — Área do Cliente

**Data:** 2026-08-11  
**Âmbito:** Vue Client + API dashboard client (extensão mínima)  
**Fora de âmbito:** Google Calendar, Admin visual, Public Home, legacy-csharp/

---

## 1. Rotas client encontradas

| ROTA | NAME | VIEW | FUNÇÃO | ENDPOINTS |
|------|------|------|--------|-----------|
| `/panel/client` | redirect | — | → dashboard | — |
| `/panel/client/dashboard` | `client-dashboard` | `views/client/DashboardView.vue` | Início / resumo | `GET /dashboard`, `GET /notifications`, `PATCH /notifications/{id}/read`, `DELETE /appointments/{id}` |
| `/panel/client/appointments` | `client-appointments` | `views/client/AppointmentsView.vue` | Lista próximos + histórico | `GET /appointments`, `DELETE /appointments/{id}` |
| `/panel/client/account` | `client-account` | `views/account/AccountView.vue` | Perfil + password (**novo**) | `GET /auth/me`, `PATCH /auth/profile`, `PUT /auth/password` |
| `/client` → redirect | — | — | Compat | — |

`meta.shell = 'client'` em todas; **não** carregam publicAssets.

---

## 2. Funcionalidades no legado (C#)

Fonte: `PublicClientPanelController` + views `PublicClientPanel/*`.

| Funcionalidade | Legado |
|----------------|--------|
| Dashboard / Minha Área | Sim — calendário FullCalendar + serviços |
| Próximos / lista compromissos | Sim — MyAppointments + filtros |
| Histórico | Sim (mesma lista + PDF) |
| Estatísticas / gráficos | Sim — Stats.cshtml |
| Cancelar | Sim |
| Reagendar | Via PublicBooking (não no painel principal) |
| Confirmar | PublicProfile (outro fluxo) |
| Avaliar | UI MyAppointments |
| Export PDF | Sim |
| Google Calendar | Botão + stubs TODO |
| Perfil / password | Account area partilhada |
| Notificações in-app | Não evidentes no painel client |

---

## 3. Laravel API atual (relevante ao cliente)

| Endpoint | Cliente |
|----------|---------|
| `GET /api/v1/dashboard` | Contagens + **agora** `next_appointment`, `completed_appointments` |
| `GET /api/v1/appointments` | Scoped ao `client_id` |
| `DELETE /api/v1/appointments/{id}` | Cancelar (policy) |
| `GET/PATCH /api/v1/notifications*` | Sim |
| `PATCH /auth/profile`, `PUT /auth/password` | Qualquer autenticado |
| Reagendar / confirmar / stats / PDF / Google | **Não** |

Estados appointment: `pending`, `confirmed`, `completed`, `canceled`.

---

## 4. Funcionalidades Vue (após redesign)

| Área | Estado |
|------|--------|
| Dashboard intro + CTA booking | Sim |
| Próximo agendamento (detalhe) | Sim |
| Resumo próximos / realizados / notificações | Sim |
| Notificações compactas + marcar lida | Sim |
| Meus agendamentos (próximos + histórico) | Sim |
| Cancelar | Sim |
| Perfil / password | Sim (`/panel/client/account`) |
| Header com AccountMenu | Sim |

---

## 5. Matriz de comparação / GAPs

| FUNCIONALIDADE | LEGADO C# | LARAVEL API | VUE CLIENT | STATUS |
|----------------|-----------|-------------|------------|--------|
| Dashboard cliente | Sim | Parcial→estendido | Sim | **MIGRADO** (sem calendário) |
| Próximo agendamento | Implícito no calendar | Agora no dashboard | Sim | **MIGRADO** |
| Lista / histórico | Sim | Sim | Sim | **MIGRADO** |
| Cancelar | Sim | Sim | Sim | **MIGRADO** |
| Notificações | — | Sim | Sim | **SUBSTITUÍDO** (melhor que legado) |
| Perfil / password | Sim | Sim | Sim | **MIGRADO** |
| Novo agendamento | Link booking | Public booking API | CTA `/booking` | **MIGRADO** |
| FullCalendar | Sim | Não | Não | **GAP** (não necessário agora) |
| Stats / charts | Sim | Não | Não | **GAP** |
| Export PDF | Sim | Não | Não | **GAP** |
| Avaliar serviço | Sim | Não | Não | **GAP** |
| Reagendar | PublicBooking | Não endpoint dedicado | Não | **GAP** |
| Confirmar (cliente) | PublicProfile | Não | Não | **GAP** / **NÃO NECESSÁRIO** se fluxo for admin |
| Google Calendar | Stub | Não | Não | **GAP** futuro (via Laravel) |
| Filtros data/status | Sim | Query `status` existe | UI filtros não | **PARCIAL** |

---

## 6. Causa do layout estreito

`ClientShell.vue` usava `max-w-5xl` (**1024px**) no header e no `<main>`.

**Correção:** `.client-page-container` com `max-width: 82.5rem` (**1320px**) + padding 16 / 24 / 32px.

Não foram usadas margins mágicas.

---

## 7. ClientLayout antes / depois

**Antes:**
```
ClientLayout → ClientShell (header inline + max-w-5xl + Sair na nav)
```

**Depois:**
```
ClientLayout
└── ClientShell
    ├── ClientHeader (brand + nav + AccountMenu)
    └── client-page-container → RouterView
```

---

## 8. Header

- Logo + Salon System  
- Nav: Início | Meus agendamentos  
- AccountMenu: Minha conta, Editar perfil, Alterar palavra-passe, Sair  
- Mobile: menu + dropdown conta  
- Sem “Sair” solto na nav principal  

---

## 9–12. Dashboard / próximo / notificações / agendamentos

- Intro compacta com `first_name`  
- Cards resumo compactos  
- Bloco “Próximo agendamento” com empty state + CTA  
- Notificações em lista (título, body, data, marcar lida)  
- Appointments: secções Próximos (cards) + Histórico (lista)  
- Badges PT: Pendente / Confirmado / Concluído / Cancelado  

---

## 13. Perfil

- Rota `client-account` reutiliza `AccountView.vue`  
- Endpoints auth existentes (sem duplicar)  
- AccountMenu com prop `accountRouteName`  

---

## 14. Componentes reutilizados

| Componente | Uso |
|------------|-----|
| `UserAvatar` | Shared (já genérico) |
| `AccountMenu` | Shared + `accountRouteName` |
| `AccountView` | Admin + Client |
| `AppButton` / `AppBadge` / `EmptyState` / … | Shared |

Sem `client.css` obrigatório — Tailwind + scoped container no shell.

---

## 15. Endpoints

**Utilizados:** dashboard, appointments, notifications, auth profile/password, cancel appointment.  
**Criados:** nenhum recurso novo.  
**Alterado:** `DashboardController` (cliente) — `next_appointment`, `completed_appointments`.

---

## 16–17. Ficheiros

**Criados:**
- `components/layout/ClientHeader.vue`
- `docs/CLIENT_PANEL_REDESIGN.md` (este)

**Alterados:**
- `ClientShell.vue`, `ClientLayout.vue`
- `DashboardView.vue`, `AppointmentsView.vue`
- `AccountMenu.vue`, `AccountView.vue` (clear query)
- `router/index.ts`
- `types/api.ts`
- `DashboardController.php`

---

## 18. Responsividade

- Container até 1320px; padding responsivo  
- Header sticky; nav colapsa &lt; md  
- Dashboard: grelha 7/5 em lg  
- Appointments: 2 colunas md, 1 mobile  

---

## 19–20. Testes / build

- `php artisan test --filter=AuthProfile` — OK  
- `npm run build` — OK  

---

## 21. Pendências reais (GAPs)

1. FullCalendar no client  
2. Estatísticas / gráficos  
3. Export PDF  
4. Reagendar  
5. Avaliar serviço  
6. Filtros UI na lista  
7. Google Calendar (**fase futura:** Client → Laravel → Google; nunca Client → Google direto)

Nenhuma decisão deste redesign bloqueia Google Calendar posterior: appointments continuam na API Laravel.

---

*Fim do relatório.*
