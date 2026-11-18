<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payable */
class PayableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'display_status' => $this->displayStatus(),
            'display_status_label' => $this->displayStatusLabel(),
            'is_overdue' => $this->isOverdue(),
            'is_paid' => $this->isPaid(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'notes' => $this->notes,
            'supplier_id' => $this->supplier_id,
            'professional_id' => $this->professional_id,
            'appointment_id' => $this->appointment_id,
            'created_by' => $this->created_by,
            'paid_by' => $this->paid_by,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'is_active' => $this->supplier->is_active,
            ] : null),
            'professional' => $this->whenLoaded('professional', fn () => $this->professional ? [
                'id' => $this->professional->id,
                'name' => $this->professional->name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'full_name' => $this->creator->full_name ?? trim(($this->creator->first_name ?? '').' '.($this->creator->last_name ?? '')),
            ] : null),
            'payer' => $this->whenLoaded('payer', fn () => $this->payer ? [
                'id' => $this->payer->id,
                'full_name' => $this->payer->full_name ?? trim(($this->payer->first_name ?? '').' '.($this->payer->last_name ?? '')),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
