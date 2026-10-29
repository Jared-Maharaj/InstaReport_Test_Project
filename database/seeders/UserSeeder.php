<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = now();

        $users = [
            ['name' => 'Test User 1', 'email' => 'instareportuser1@yopmail.com', 'role' => 'USER'],
            ['name' => 'Test User 2', 'email' => 'instareportuser2@yopmail.com', 'role' => 'USER'],
            ['name' => 'Test User 3', 'email' => 'instareportuser3@yopmail.com', 'role' => 'USER'],
            ['name' => 'Test User 4', 'email' => 'instareportuser4@yopmail.com', 'role' => 'USER'],
            ['name' => 'Test User 5', 'email' => 'instareportuser5@yopmail.com', 'role' => 'USER'],
            ['name' => 'Test User 6', 'email' => 'instareportuser6@yopmail.com', 'role' => 'USER'],
            ['name' => 'Jared Maharaj', 'email' => 'jaredmaharaj54@gmail.com', 'role' => 'USER'],
            ['name' => 'Test Area Manager', 'email' => 'instareportuser0@yopmail.com', 'role' => 'AREA_MANAGER'],
            ['name' => 'Area Manager', 'email' => 'jaredmaharaj22@gmail.com', 'role' => 'AREA_MANAGER'],
        ];

        User::query()->insertOrIgnore(array_map(
            static fn (array $user): array => [
                ...$user,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $users,
        ));
    }
}