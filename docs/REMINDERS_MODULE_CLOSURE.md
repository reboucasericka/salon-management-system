# REMINDERS — Fecho do módulo (2026-08-11)

## Regra real no C# (auditoria)

| Aspeto | Realidade |
|---|---|
| Runner | `BackgroundService` a cada **1 hora** (`Program.cs` HostedService) |
| 24h | Janela **24h–25h** antes (`UtcNow`) |
| 2h | Janela **2h–3h** antes |
| Status | `agendado` / `scheduled` / `confirmado` / `confirmed` |
| Destinatário | **Customer.Email** apenas |
| Tawk.to | Opcional (`AppointmentReminders:NotifyTawk`) — **não migrado** |
| `ReminderSent` | Boolean único; marcado `true` após envio |
| Gap crítico | Após 24h, `ReminderSent=true` → **2h nunca enviava** |
| Tabela `Reminder` | Estrutura (SendDate/SendMethod/Status) — **nunca escrita** pelo serviço |
| Flags config | `Reminder24hEnabled` / `Reminder2hEnabled` — **não lidas** no código |
| SMS | Não no fluxo do hosted service |
| Retry | Catch + log; sem fila de retry formal |
| Profissional | **Não** recebia reminder |

## Matriz

| FUNCIONALIDADE | LEGADO | LARAVEL | ESTADO |
|---|---|---|---|
| 24h + 2h | Intenção sim; 2h bloqueado | Independentes por tipo | MIGRADO + corrigido |
| Janela 1h | Sim | Sim (configurável) | MIGRADO |
| Destinatário cliente | Sim | Sim | MIGRADO |
| Tabela Reminder | Morta | `appointment_reminders` | SUBSTITUÍDO |
| `reminder_sent` | Boolean único | Mantido (compat; true se algum enviado) | MIGRADO |
| Tawk | Sim | Não | DESCARTÁVEL |
| Scheduler | Loop infinito | Laravel Schedule cada 10 min | SUBSTITUÍDO |

---

## Relatório final

1. **Regra C#:** hosted service horário; janelas 24–25h e 2–3h; email cliente; `!ReminderSent`.
2. **24h/2h:** ambos previstos; 2h falhava por boolean único.
3. **Destinatários:** cliente apenas.
4. **Elegíveis:** pending/confirmed (map de scheduled/confirmed); não canceled/completed.
5. **Modelagem:** `appointment_reminders` + enum `ReminderType`.
6. **Migration:** `2026_08_11_220000_create_appointment_reminders_table`.
7. **`reminder_sent`:** mantido; passa a `true` quando **qualquer** reminder é enviado; detalhe por tipo na tabela nova / API `reminders` no show.
8. **Command:** `php artisan appointments:send-reminders`.
9. **Scheduler:** a cada 10 minutos em `routes/console.php`.
10. **Timezone:** `config('app.timezone')` (default UTC). Settings centralizará (ex. Europe/Lisbon).
11. **Mailable:** `AppointmentReminderMail` + Blade markdown.
12. **Idempotência:** unique `(appointment_id, type)` + `sent_at` not null.
13. **Concorrência:** transaction + `lockForUpdate` no appointment e no reminder.
14. **Falhas:** não marca `sent_at`; incrementa `attempts` / `last_error`; retry até `max_attempts`.
15. **Queue:** **NÃO** obrigatória (envio síncrono); Mailable pronto para `ShouldQueue`.
16. **Criados:** enum, model, migration, config/appointments.php, service, command, mail, view, test, doc.
17. **Alterados:** Appointment, AppointmentResource, AppointmentController@show, console.php, .env.example.
18. **Testes:** 9 cenários (24h, 2h independente, cancel/complete, fora janela, sem email, falha SMTP, pending, summary, timezone).
19. **`php artisan test`:** **145 passed** (713 assertions).
20. **Comandos:**
    - Local: `php artisan appointments:send-reminders` ou `php artisan schedule:work`
    - Produção: cron `* * * * * php artisan schedule:run`
21. **Limitações:** sem Tawk; sem SMS; sem UI admin de reminders; timezone ainda UTC no app.
22. **Settings depois:** timezone, enable 24h/2h, intervalos, contacto/remetente, max_attempts.
23. **Readiness Settings:** config já isolada em `config/appointments.php` + env flags.
24. **Readiness Google:** horários em `start_time` + timezone app; sem `google_event_id` nesta fase.
