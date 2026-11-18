# MAIL + RESET PASSWORD — Fecho (2026-08-11)

## Matriz Legado × Laravel

| FUNCIONALIDADE | LEGADO | LARAVEL | ESTADO | AÇÃO |
|---|---|---|---|---|
| SMTP config | appsettings/UserSecrets + 2 serviços | `.env` + Laravel Mail | MIGRADO | Nativo |
| Forgot password | Identity token, 3h, anti-enumeration | Password broker + `password_reset_tokens` | MIGRADO | Completo |
| Reset password | AccountController | `POST /auth/reset-password` | MIGRADO | Completo |
| UI forgot/reset | Razor views | `/forgot-password`, `/reset-password` | MIGRADO | Auth shell |
| Booking confirmation | Duplo envio (2 serviços) → cliente | 1 Mailable → cliente | MIGRADO | Sem duplicar |
| Cancellation email | Vários fluxos inconsistentes | destroy/update→canceled, 1× | MIGRADO | Anti-duplicado |
| Reschedule email | Existia no PublicBooking | Sem fluxo dedicado | AUSENTE | Fase futura |
| Activation email | Obrigatória no registo público | Conta usável já no register | DESCARTÁVEL agora | Documentado |
| Reminders 24h/2h | HostedService | `reminder_sent` campo só | AUSENTE | Reminders |
| Google Calendar | — | — | AUSENTE | Fora de escopo |
| Change password autenticado | — | `PUT /auth/password` | MIGRADO | Sem duplicar |

---

## Relatório final (25 pontos)

### 1. Email real no C#
Dois SMTP paralelos (`EmailService` + `CommunicationService`), templates Razor em `Views/Emails` mortos (HTML inline), confirmação/cancelamento enviados ao **cliente** (por vezes em duplicado), reset via Identity (3h), ativação obrigatória no registo público.

### 2. Flows ativos / mortos
**Ativos:** forgot/reset, activation (legado), booking confirm/cancel (cliente), reminders background.  
**Mortos/incompletos:** Razor Emails, flags Reminder24h/2h não lidas, envio duplo, cancel sem email em alguns controllers.

### 3. Mail Laravel escolhido
Mailables + Notification (`ResetPasswordNotification`) + `Mail` facade. Sem SMTP artesanal.

### 4. Configuração
`MAIL_*` + `FRONTEND_URL` em `.env.example`. Default local: `MAIL_MAILER=log`. Testes: `array`.

### 5. Forgot endpoint
`POST /api/v1/auth/forgot-password` — throttle 5/min — mensagem genérica.

### 6. Reset endpoint
`POST /api/v1/auth/reset-password` — email, token, password, password_confirmation.

### 7. Token strategy
Broker Laravel `password_reset_tokens`; expire **180 min** (paridade legado 3h); throttle broker 60s; hash do token; tokens Sanctum revogados no sucesso.

### 8. Frontend forgot
`/forgot-password` — Auth shell — mensagem genérica de sucesso.

### 9. Frontend reset
`/reset-password?token=&email=` — sucesso → login + toast.

### 10. Booking confirmation
Após create (público + autenticado): `BookingConfirmationMail` → email do cliente.

### 11. Cancellation
`DELETE` (e update para canceled): `BookingCancelledMail` uma vez; já cancelado → sem reenvio.

### 12. Reschedule
**NÃO** — existe update genérico, mas sem fluxo/UI de reagendamento dedicado; não inventar email.

### 13. Recipients
**Cliente** apenas (confirmado no legado). Sem email a profissional/admin nesta fase.

### 14. Activation
**NÃO agora** — register Laravel já cria cliente utilizável; ativação obrigatória exigiria planeamento de UX/login.

### 15. Queue
**NÃO nesta fase** (envio síncrono). Queue `database` existe; Mailables/Notification preparados para `ShouldQueue` futuro.

### 16. Falha SMTP
Appointment/create/cancel persistem; email em try/catch + log; API não faz rollback do negócio.

### 17. Templates
`resources/views/emails/{booking-confirmation,booking-cancelled,reset-password}.blade.php` (Markdown mail).

### 18. Ficheiros criados (principais)
- `app/Mail/BookingConfirmationMail.php`, `BookingCancelledMail.php`
- `app/Notifications/ResetPasswordNotification.php`
- `app/Services/BookingEmailService.php`
- Requests forgot/reset; views emails; `ForgotPasswordView.vue`, `ResetPasswordView.vue`
- `PasswordResetTest.php`, `BookingEmailTest.php`
- `docs/MAIL_RESET_PASSWORD_CLOSURE.md`

### 19. Ficheiros alterados (principais)
- `AuthController`, `User`, `routes/api.php`, `config/auth.php`, `config/app.php`
- `AppointmentController`, `PublicBookingController`
- `.env.example`, `phpunit.xml`
- `LoginView.vue`, `router/index.ts`

### 20. Testes
Forgot (existente/inexistente/validação), reset (válido/inválido/expirado/confirmação), booking mail, falha SMTP sem rollback, cancel 1×, skip sem email.

### 21. `php artisan test`
**136 passed** (688 assertions)

### 22. `npm run build`
**OK**

### 23. Limitações
Sem branding Settings; sem email a profissionais; sem reschedule; sem activation; envio síncrono; contacto do salão ainda via `APP_NAME` apenas.

### 24. Fica para Reminders
`AppointmentReminderService` (24h/2h), `reminder_sent`, scheduler, eventual Tawk — **não nesta execução**.

### 25. Readiness próxima fase
Mail/reset base pronta. Próximo: **Reminders** (depois Settings/Reports/Google conforme auditoria V2).
