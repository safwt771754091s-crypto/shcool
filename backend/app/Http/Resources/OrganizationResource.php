<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type,
            'level' => $this->level,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'path' => $this->path,
            'depth' => $this->depth,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'children' => OrganizationResource::collection($this->whenLoaded('children')),
            'parent' => new OrganizationResource($this->whenLoaded('parent')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
