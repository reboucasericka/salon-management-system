# COMMISSIONS — Fecho do módulo (2026-08-11)

## Matriz Legado × Laravel

| FUNCIONALIDADE | LEGADO | LARAVEL | ESTADO | AÇÃO |
|---|---|---|---|---|
| Comissão global profissional | `DefaultCommission` (usado) | `professionals.commission_percentage` | MIGRADO | Manter |
| `CommissionPercentage` no Professional | Campo morto | Não mapeado | DESCARTÁVEL | Não migrar |
| Comissão por serviço (pivot) | `ProfessionalService.Commission` (Index sim; Edit não gravava) | `professional_service.commission_percentage` | MIGRADO | Pivot usável |
| Comissão no `Service` | Campo sem cálculo | Ausente | DESCARTÁVEL | Não migrar |
| Settings DefaultExtension/DesignCommission | Config sem uso no calc | Ausente | DESCARTÁVEL | Não migrar |
| Fallback null → global | Implícito via DefaultCommission | pivot null → professional → 0 | MIGRADO | Regra final |
| Cálculo | `Service.Price * DefaultCommission/100` (perfil público) | `coalesce(appointment.total_price, service.price)` | SUBSTITUÍDO | Decisão de migração |
| Payable automático | Não | Não | MIGRADO | Sem automação |
| Payable manual Commission | Sim (AdminPayables) | `POST /commissions/generate` + Payables | MIGRADO | Manual + idempotência |
| Pagamento | Mark paid em Payables | `POST /payables/{id}/pay` | MIGRADO | Reutilizar |
| Preview/consulta | Relatório + index | `GET /commissions` | MIGRADO | Completo |
| Relatório PDF/Excel | AdminReports/Commissions | — | AUSENTE | Fica para Reports |
| UI admin | Forms + report | Professionals + Commissions | MIGRADO | Completo |
| Histórico pago | Amount no Payable | Snapshot em `amount` + notes | MIGRADO | Sem recalcular |

---

## Relatório final (25 pontos)

### 1. Como funcionava no C#
- Comissão efetiva usada: **`Professional.DefaultCommission`** (percentual).
- Cálculo de ganhos no perfil público: `Service.Price * DefaultCommission / 100`.
- `ProfessionalService.Commission` existia na UI de listagem, mas o Edit **não persistia** o valor.
- Payables tipo Commission eram **criados manualmente**; o relatório lia Payables.
- **Sem** geração automática ao concluir Sale/Appointment.

### 2. Campos mortos / inconsistências
- `Professional.CommissionPercentage` — nunca gravado nos forms.
- `Service.Commission` — sem uso em cálculo.
- Settings `DefaultExtensionCommission` / `DefaultDesignCommission` — config sem cálculo de appointments.
- Pivot `Commission` no legado — estrutura incompleta (mostrar ≠ gravar).

### 3. Precedência final
1. `professional_service.commission_percentage` (inclui **0** explícito)
2. senão `professionals.commission_percentage`
3. senão `0`  
`null` no pivot = fallback. Serviço não associado = não aplica.

### 4. Base de cálculo
- **Decisão de migração:** `coalesce(appointment.total_price, service.price)`.
- Legado usava sobretudo `Service.Price`; o Laravel privilegia o total efetivo do agendamento quando existe.

### 5. Estado anterior no Laravel
- Pivot + form global + `CommissionService` + preview `GET /commissions` + UI parcial.
- Estados financeiros hardcoded (“Não gerado”); sem `appointment_id` em payables; sem generate.

### 6. Estado final
- Configuração (global + por serviço), preview com origem/%/base/estado, geração **manual** de Payables, pagamento via Payables, testes e build verdes.

### 7. Migrations
- Reutilizada: `2026_08_11_160000_add_commission_percentage_to_professional_service_table`
- Nova: `2026_08_11_210000_add_appointment_id_to_payables_table` (`appointment_id` + unique `(type, appointment_id)`)
- `professionals.commission_percentage` já existia desde a create table.

### 8. Pivot
- `professional_service.commission_percentage` nullable; sync preserva associações e comissões.

### 9. Professional
- Campo “Comissão padrão (%)” com ajuda: usada quando o serviço não tem específica; validação 0–100.

### 10. CommissionService
- `resolveCommissionPercentage`, `calculateCommissionAmount`, `calculateForAppointment`, `previewFromCompletedAppointments`, `generatePayables` (manual).

### 11. Integração Payables
- `PayableType::Commission`; `professional_id` + `appointment_id`; pagamento `markAsPaid` existente.

### 12. Payable automático
- **NÃO**

### 13. Motivo
- Legado só criava Commission Payables manualmente; inventar automação quebraria a paridade.

### 14. Evento oficial de geração
- **Nenhum automático.** Evento = ação admin `POST /api/v1/commissions/generate` sobre appointments **completed**.
- Fonte financeira oficial do preview/generate: **Appointment** (não Sale).

### 15. Idempotência
- Unique `(type, appointment_id)` + check pré-create; re-generate → `already_exists`.
- Cancelados / não completed pedidos explicitamente → `skipped` (`canceled` / `not_completed`).
- Comissão zero → `zero_amount`.

### 16. Histórico
- `payables.amount` = snapshot; notes com base/%/origem. Preview de pagos usa o Payable, não recalcula com % atuais para o valor pago (estado `paid` vem do Payable).

### 17. Endpoints
- `GET /api/v1/commissions` — preview (admin)
- `POST /api/v1/commissions/generate` — geração manual (admin)
- Sync profissional-serviços já aceita `commission_percentage`
- Pagamento: rotas Payables existentes

### 18. UI
- `/panel/admin/commissions` — filtros, tabela, seleção, Gerar Payables, estados reais
- Professionals — comissão padrão + Gerir Serviços com % específica

### 19. Ficheiros criados (principais)
- `backend/database/migrations/2026_08_11_210000_add_appointment_id_to_payables_table.php`
- `docs/COMMISSIONS_MODULE_CLOSURE.md`

### 20. Ficheiros alterados (principais)
- `CommissionService.php`, `CommissionController.php`, `Payable.php`, `PayableResource.php`
- `routes/api.php`, `CommissionTest.php`
- `CommissionsView.vue`, `salonApi.ts`, `types/api.ts`
- (já existentes reutilizados: ProfessionalsView, pivot migration, enums, policies)

### 21. Testes
- Pivot vs global, null vs 0, 422, sync, cálculo, preview, generate, idempotência, canceled/zero, auth roles, pay via Payables.

### 22. `php artisan test`
- **125 passed** (650 assertions)

### 23. `npm run build`
- **OK** (exit 0)

### 24. Limitações
- Sem cálculo a partir de Sale/SaleItem nesta fase (fonte = Appointment).
- Sem app do profissional consultar comissões.
- Sem export/relatório formal.
- Preview continua a calcular % a partir da config atual para linhas ainda só “calculated”; após Payable, amount é snapshot.

### 25. O que fica para Reports
- Equivalente a `AdminReports/Commissions` (PDF/Excel/CSV), agregações por período, exportações e relatórios gerenciais.
