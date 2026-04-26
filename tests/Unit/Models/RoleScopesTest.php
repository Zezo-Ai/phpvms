<?php

declare(strict_types=1);

use App\Models\Role;

test('Role::byName finds a role case-sensitively', function () {
    Role::factory()->create(['name' => 'admin', 'guard_name' => 'web']);
    Role::factory()->create(['name' => 'pilot', 'guard_name' => 'web']);

    $result = Role::byName('admin')->first();

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('admin');
});

test('Role::byName returns null for unknown role', function () {
    Role::factory()->create(['name' => 'admin', 'guard_name' => 'web']);

    $result = Role::byName('nonexistent')->first();

    expect($result)->toBeNull();
});
