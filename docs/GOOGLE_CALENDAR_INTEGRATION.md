# Google Calendar Integration

## 1. Comportamento encontrado no C# (legado)

- Auth: OAuth via `GoogleWebAuthorizationBroker` + `FileDataStore` local (não adequado a servidor Laravel).
- Um único `CalendarId` global (`GoogleCalendar:CalendarId`, fallback `primary`).
- Outbound: `CreateOrUpdateEventAsync` / `DeleteEventAsync` após create/update/cancel em vários controllers.
- Campo `Appointment.GoogleEventId`.
- Timezone hardcoded `Europe/Lisbon`.
- Inbound: `SyncFromGoogleAsync` só atualiza horário se existir `extendedProperties.private.appointmentId`; **não cria** appointments. Webhook era esqueleto.
- Lacunas: alguns fluxos públicos/admin não sync; falha Google não revertia appointment.

## 2. Estratégia Laravel

OAuth 2.0 Authorization Code (admin) → tokens encrypted em `google_calendar_connections` → sync outbound após persistência local.

Pacote: `google/apiclient` **2.18** (OAuth/`Google\Client`). Chamadas Calendar via REST HTTP (sem depender de `google/apiclient-services` no runtime da app — evita o pacote monolítico problemático em Windows).

## 3. Source of truth

**`appointments` Laravel** é a fonte oficial. Google Calendar é projeção externa.

Fluxo: Client/Admin Vue → Laravel API → appointments → Google Calendar.

## 4. OAuth

1. Admin → `GET /api/v1/google-calendar/connect` → `authorization_url`
2. Google consent
3. Callback browser: `GET /google-calendar/callback` (valida `state` em cache)
4. Admin seleciona calendário: `PUT /api/v1/google-calendar/calendar`
5. Integração `ready`

## 5. Scopes (mínimos)

- `https://www.googleapis.com/auth/calendar.events`
- `https://www.googleapis.com/auth/calendar.calendarlist.readonly`

Sem Gmail. Sem drive.

## 6. Storage de tokens

Tabela `google_calendar_connections`:

- `access_token` / `refresh_token` com cast `encrypted`
- `calendar_id`, `calendar_name`, `google_account_email`, `is_active`, `last_synced_at`
- Secrets de app (`GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`) só no `.env`

Settings **não** guarda tokens.

## 7. Timezone

| Camada | Valor |
|--------|--------|
| DB / app runtime | `APP_TIMEZONE` (tipicamente UTC) |
| Business | `SettingsService::timezone()` |
| Google Event | mesma timezone business em `Start`/`End.TimeZone` |

## 8. Appointment → Google Event

- Summary: `{Service} — {Client}`
- Description: serviço, cliente, profissional, valor, estado, Appointment ID
- Extended private: `appointmentId`, `source`
- Duração: `start_time` / `end_time` reais

## 9. Create / Update / Cancel

| Ação local | Google |
|------------|--------|
| Create (admin + público) | createOrUpdate (insert) |
| Update relevante | createOrUpdate (update pelo `google_event_id`) |
| Cancel / destroy lógico | `events.delete`; limpa `google_event_id` |

Orquestração: `AppointmentGoogleSyncService` nos controllers de mutação. Falha Google **não** desfaz o appointment.

## 10. Idempotência

Se existe `google_event_id` → update. Retry command não cria duplicados para appointments já `synced`.

## 11. Failure / Retry

- Status: `pending` \| `synced` \| `failed` \| `not_applicable`
- Command: `php artisan appointments:sync-google`
- Scheduler: a cada 15 minutos

## 12. Inbound sync

**NÃO** nesta fase. Eventos criados só no Google **não** viram appointments comerciais.

GAP futuro: “calendar blocks” / indisponibilidade ≠ Appointment.

## 13. Configuração Google Cloud Console

1. Criar projeto Google Cloud
2. Ativar **Google Calendar API**
3. OAuth consent screen (External/Internal conforme conta)
4. Credenciais → OAuth client ID → **Web application**
5. Authorized redirect URI: valor de `GOOGLE_REDIRECT_URI` (ex. `http://127.0.0.1:8000/google-calendar/callback`)
6. Copiar Client ID / Secret para `.env` local (nunca commit)

## 14. `.env`

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/google-calendar/callback"
```

## 15. Teste manual com conta real

1. Admin → Definições → Google Calendar → Conectar
2. Autorizar conta do salão
3. Selecionar calendário → Guardar
4. Criar appointment no Admin → confirmar evento no Google
5. Alterar horário → mesmo evento atualizado
6. Cancelar → evento removido no Google
7. Booking público → evento criado
8. Cliente cancela (painel) → evento removido
9. Desconectar → Laravel continua; appointments preservados; eventos históricos no Google **não** são apagados em massa

## 16. Segurança

- Tokens encrypted at rest
- Secrets fora da BD
- Scopes mínimos
- Só admin
- OAuth `state` one-time em cache
- API status **nunca** devolve tokens
- Logs sem tokens

## 17. App Profissional (futuro)

App → Laravel API → appointments → Google. Nunca App → Google direto.

## Desconexão — decisão

Revoga token quando possível; limpa credenciais locais; **não** apaga appointments; **não** apaga em massa eventos Google já criados (evita destruição acidental do histórico do calendário do salão).

## Calendário por profissional

Não nesta fase (legado também era 1 calendário global). GAP documentado para o futuro.
