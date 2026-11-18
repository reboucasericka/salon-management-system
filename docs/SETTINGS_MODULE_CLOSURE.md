# SETTINGS — Fecho do módulo (2026-08-21)

## 1. Estrutura real no C#
Entidade `Setting` (tabela `Settings`): ClinicName, Email, LandlinePhone, WhatsAppPhone, Address, Logo, Icon, ReportLogo, ReportType, Instagram, CommissionType, DefaultExtensionCommission, DefaultDesignCommission, ImagesFolder, BusinessHours, DefaultServiceDuration.

## 2. Campos realmente utilizados no legado
**Quase nenhum.** O CRUD admin lia/escrevia a tabela, mas o resto da app **não consumia** `Setting`. Reminder flags e email/Google vinham de `IConfiguration`/`appsettings`, não da BD.

## 3. Código morto/incompleto
Logo/Icon/ReportLogo (sem UI/consumo); ReportType; CommissionType; DefaultExtension/DesignCommission (comissão real = Professional); ImagesFolder; BusinessHours string (agenda = ProfessionalSchedule); DefaultServiceDuration; flags Reminder24h/2h no JSON **não lidas** pelo serviço.

## 4. Campos descartados e motivo
| Campo legado | Motivo |
|---|---|
| Logo/Icon/ReportLogo | Mortos; sem upload Settings nesta fase |
| ReportType | Reports futuros |
| Commission* defaults | Já resolvido em Commissions |
| ImagesFolder | Storage Laravel |
| BusinessHours string | Horários por profissional |
| DefaultServiceDuration | Duração no Service |
| WhatsApp/Instagram | Sem efeito real sem refactor amplo do público |

## 5. Arquitetura Laravel
Singleton `settings` + `SettingsService` (cache 60s). Sem CRUD multi-registo.

## 6. Migration
`2026_08_21_120000_create_settings_table`

## 7. Campos finais
`salon_name`, `timezone`, `currency`, `contact_email`, `contact_phone`, `address`, `reminder_24h_enabled`, `reminder_2h_enabled`

## 8. Defaults
- timezone null → `config('app.timezone')` / `APP_TIMEZONE`
- currency → `EUR`
- reminders → `true` (paridade config/env atual)
- branding contacto → null (sem inventar)

## 9. SettingsService
`current`, `update`, `timezone`, `salonName`, `currency`, `reminder24h/2hEnabled`, `isReminderTypeEnabled`, `publicPayload`, `isValidTimezone`

## 10. Timezone
IANA validada (`Rule::in(timezone_identifiers_list())`). Operacional via Settings; fallback app. Reminders comparam janelas em UTC.

## 11. Integração Reminders
`AppointmentReminderService` usa SettingsService para timezone + flags. Tabela/tipos/idempotência/janelas **intocados**.

## 12. Integração Mail
Mailables/Notification usam `salonName()` e timezone Settings (fallback `APP_NAME` / app timezone).

## 13. Endpoints
- `GET/PATCH /api/v1/settings` (admin)
- `GET /api/v1/public/settings` (campos seguros)

## 14. Policy
Admin only (`SettingPolicy`)

## 15. Página admin
`/panel/admin/settings` — Geral / Contacto / Lembretes

## 16. Sidebar
AdminShell → Administração → Definições

## 17. Endpoint público
**SIM** — sem flags de reminder / sem internos

## 18. Secrets
SMTP/Google/API keys **fora** da BD (`.env`)

## 19. Criados
Migration, Model, SettingsService, Policy, Controller, Requests/Resources, SettingsView, SettingsTest, doc

## 20. Alterados
AppServiceProvider, routes/api, AppointmentReminderService, Mailables, ResetPasswordNotification, email blades, AdminShell, router, salonApi, publicApi, types, PublicFooter, config/app, appointments, .env.example

## 21. `php artisan test`
**153 passed** (757 assertions)

## 22. `npm run build`
**OK**

## 23. Limitações
Sem logo upload; horário comercial string não migrado; Instagram/WhatsApp não centralizados; branding público só no footer (outros hardcodes permanecem até refactor gradual).

## 24. Readiness Reports
ReportType/ReportLogo deliberadamente **fora**. Settings base OK para Reports consumirem nome/moeda depois.

## 25. Readiness Google Calendar
Timezone operacional IANA pronta (`Europe/Lisbon` etc.). Secrets Calendar continuam no `.env`. **Não iniciado nesta execução.**
