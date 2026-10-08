<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'name' => $this->name,
            'total_points' => (float) $this->total_points,
            'rank' => $this->rank,
            'previous_rank' => $this->previous_rank,
            'rank_delta' => $this->rank_delta,
            'sample_size' => $this->sample_size,
            'breakdown' => $this->breakdown,
            'computed_at' => $this->computed_at?->toIso8601String(),
        ];
    }
}
