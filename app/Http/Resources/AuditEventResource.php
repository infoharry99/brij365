<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'action' => $this->action,
            'metadata' => $this->metadata ?? [],
            'ip_address' => $this->ip_address,
            'request_method' => $this->request_method,
            'request_path' => $this->request_path,
            'request_id' => $this->request_id,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role?->slug,
                'company_id' => $this->user->company_id,
            ] : null),
        ];
    }
}
