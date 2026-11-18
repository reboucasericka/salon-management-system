<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Receivable */
class ReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'received_at' => $this->received_at?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'display_status' => $this->displayStatus(),
            'display_status_label' => $this->displayStatusLabel(),
            'is_overdue' => $this->isOverdue(),
            'is_received' => $this->isReceived(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'service_id' => $this->service_id,
            'sale_id' => $this->sale_id,
            'created_by' => $this->created_by,
            'received_by' => $this->received_by,
            'reference_label' => $this->referenceLabel(),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'is_active' => $this->client->is_active,
            ] : null),
            'professional' => $this->whenLoaded('professional', fn () => $this->professional ? [
                'id' => $this->professional->id,
                'name' => $this->professional->name,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'sale' => $this->whenLoaded('sale', fn () => $this->sale ? [
                'id' => $this->sale->id,
                'total_amount' => $this->sale->total_amount,
                'status' => $this->sale->status?->value,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ] : null),
            'receiver' => $this->whenLoaded('receiver', fn () => $this->receiver ? [
                'id' => $this->receiver->id,
                'full_name' => $this->receiver->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
