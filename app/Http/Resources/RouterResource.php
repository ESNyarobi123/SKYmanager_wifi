<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouterResource extends JsonResource
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
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'hotspot_ssid' => $this->hotspot_ssid,
            'is_online' => (bool) $this->is_online,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'active_plan' => $this->whenLoaded('subscriptions', function () {
                $sub = $this->subscriptions->firstWhere('status', 'active');

                return $sub ? [
                    'plan_name' => $sub->plan->name ?? null,
                    'expires_at' => $sub->expires_at?->toIso8601String(),
                ] : null;
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
