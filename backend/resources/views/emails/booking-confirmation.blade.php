<x-mail::message>
# Confirmação de agendamento

Olá {{ $appointment->client?->name ?? 'Cliente' }},

O seu agendamento em **{{ $salonName }}** foi registado com sucesso.

**Serviço:** {{ $appointment->service?->name ?? '—' }}  
**Profissional:** {{ $appointment->professional?->name ?? '—' }}  
**Data:** {{ $appointment->start_time?->timezone($timezone ?? config('app.timezone'))->format('d/m/Y') ?? '—' }}  
**Hora:** {{ $appointment->start_time?->timezone($timezone ?? config('app.timezone'))->format('H:i') ?? '—' }}  
**Estado:** {{ $appointment->status }}

@if($appointment->total_price !== null)
**Valor:** {{ number_format((float) $appointment->total_price, 2, ',', ' ') }} €
@endif

Se precisar de alterar ou cancelar, contacte o salão ou aceda à sua área de cliente.

Obrigado,<br>
{{ $salonName }}
</x-mail::message>
