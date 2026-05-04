<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $pan = strtoupper($this->faker->bothify('?????####?'));

        return [
            'user_id' => User::factory(),
            'legal_name' => $this->faker->company().' Pvt Ltd',
            'trade_name' => $this->faker->company(),
            'pan' => $pan,
            'phone' => '9'.$this->faker->numerify('#########'),
            'address_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'pincode' => $this->faker->numerify('######'),
            'country' => 'IN',
            'entity_type' => $this->faker->randomElement(['private_ltd', 'llp', 'partnership', 'proprietorship']),
            'status' => 'draft',
        ];
    }
}
