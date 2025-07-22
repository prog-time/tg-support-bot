<?php

namespace Tests\Factories;

use Faker\Factory as Faker;

class TelegramUpdateFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Faker::create('ru_RU');

        $base = [
            'update_id' => random_int(100000000, 999999999),
            'message' => [
                'message_id' => random_int(1000, 9999),
                'from' => [
                    'id' => random_int(100000000, 999999999),
                    'is_bot' => false,
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'username' => $faker->userName,
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => random_int(100000000, 999999999),
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'username' => $faker->userName,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $faker->sentence,
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }
}
