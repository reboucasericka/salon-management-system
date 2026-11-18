<x-mail::message>
# Lembrete de agendamento

Olá {{ $appointment->client?->name ?? 'Cliente' }},

Este é um lembrete de **{{ $hoursText }}** do seu agendamento em **{{ $salonName }}**.

**Serviço:** {{ $appointment->service?->name ?? '—' }}  
**Profissional:** {{ $appointment->professional?->name ?? '—' }}  
**Data:** {{ $appointment->start_time?->timezone($timezone ?? config('app.timezone'))->format('d/m/Y') ?? '—' }}  
**Hora:** {{ $appointment->start_time?->timezone($timezone ?? config('app.timezone'))->format('H:i') ?? '—' }}

Por favor, chegue com alguns minutos de antecedência. Se precisar de alterar ou cancelar, contacte o salão o mais cedo possível.

Obrigado,<br>
{{ $salonName }}
</x-mail::message>
