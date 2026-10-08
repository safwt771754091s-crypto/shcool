<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $code = 'ORG-'.Str::upper(Str::random(6));

        return [
            'type' => Organization::TYPE_SCHOOL,
            'name' => 'مدرسة '.fake()->unique()->city(),
            'code' => $code,
            'slug' => Str::slug($code),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Organization $organization): void {
            $organization->refreshHierarchy();
        });
    }

    public function ministry(): static
    {
        return $this->state(fn () => ['type' => Organization::TYPE_MINISTRY]);
    }

    public function governorate(): static
    {
        return $this->state(fn () => ['type' => Organization::TYPE_GOVERNORATE]);
    }

    public function directorate(): static
    {
        return $this->state(fn () => ['type' => Organization::TYPE_DIRECTORATE]);
    }

    public function school(): static
    {
        return $this->state(fn () => ['type' => Organization::TYPE_SCHOOL]);
    }

    /**
     * Create a school and make it its own tenant.
     */
    public function tenant(): static
    {
        return $this->school()->afterCreating(function (Organization $school): void {
            $school->forceFill(['tenant_id' => $school->getKey()])->save();
        });
    }

    public function childOf(Organization $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->getKey()]);
    }
}
