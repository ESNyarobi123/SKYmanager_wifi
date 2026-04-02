<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->expires_at?->isPast() ?? true,
            'plan' => $this->whenLoaded('plan', fn () => [
                'name' => $this->plan->name,
                'price' => $this->plan->price,
                'duration_minutes' => $this->plan->duration_minutes,
            ]),
            'router' => $this->whenLoaded('router', fn () => [
                'id' => $this->router->id,
                'name' => $this->router->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
