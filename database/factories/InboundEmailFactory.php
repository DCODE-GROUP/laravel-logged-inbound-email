<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Database\Factories;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundEmail>
 */
class InboundEmailFactory extends Factory
{
    protected $model = InboundEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payload' => json_encode(['raw' => $this->faker->text()]),
            'provider' => 'postmark',
            'from' => [['email' => $this->faker->safeEmail(), 'name' => $this->faker->name()]],
            'to' => [['email' => $this->faker->safeEmail(), 'name' => null]],
            'cc' => [],
            'bcc' => [],
            'reply_to' => null,
            'subject' => $this->faker->sentence(),
            'text_content' => $this->faker->paragraph(),
            'html_content' => null,
            'message_id' => $this->faker->uuid(),
            'received_at' => now(),
            'organization_alias' => null,
            'tenant_id' => null,
            'status' => InboundEmailStatus::Received,
            'error' => null,
        ];
    }
}
