<?php

use Phinx\Seed\AbstractSeed;

class UserSeeder extends AbstractSeed
{
    public function run()
    {
        $faker = Faker\Factory::create();
        $data = [];
        for ($i = 0; $i < 10; $i++) {
            $data[] = [
                'username' => $faker->userName,
                'password' => password_hash('password', PASSWORD_DEFAULT),
            ];
        }
        $this->insert('user', $data);
    }
}
