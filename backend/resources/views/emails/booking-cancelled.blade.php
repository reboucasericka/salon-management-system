<x-mail::message>
# Agendamento cancelado

Olá {{ $appointment->client?->name ?? 'Cliente' }},

O agendamento abaixo em **{{ $salonName }}** foi cancelado.

**Serviço:** {{ $appointment->service?->name ?? '—' }}  
**Profissional:** {{ $appointment->professional?->name ?? '—' }}  
**Data:** {{ $appointment->start_time?->timezone($timezone ?? config('app.timezone'))->format('d/m/Y') ?? '—' }}  
**Hora:** {{ $appointment->start_time?->timezone($timezone ?? config('app.timezone'))->format('H:i') ?? '—' }}  
**Estado:** {{ $appointment->status }}

Se isto não foi intencional ou quiser remarcar, contacte o salão.

Obrigado,<br>
{{ $salonName }}
</x-mail::message>
