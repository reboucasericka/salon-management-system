<x-mail::message>
# Redefinição de palavra-passe

Olá {{ $userName }},

Recebemos um pedido para redefinir a palavra-passe da sua conta em **{{ $salonName }}**.

<x-mail::button :url="$url">
Redefinir palavra-passe
</x-mail::button>

Este link expira em **{{ $expireMinutes }} minutos**.

Se não pediu esta alteração, pode ignorar este email — a sua palavra-passe atual permanece válida.

Obrigado,<br>
{{ $salonName }}
</x-mail::message>
