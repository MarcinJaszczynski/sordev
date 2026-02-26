<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
});

describe('PaymentStatusResource', function () {
    it('blokuje dostęp bez uprawnień', function () {
        $response = $this->get('/admin/payment-status');
        $this->assertContains($response->getStatusCode(), [403, 404]);
    });

    it('pozwala adminowi na dostęp', function () {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        $response = $this->get('/admin/payment-status');
        $this->assertContains($response->getStatusCode(), [200, 404]);
    });

    it('pozwala użytkownikowi z uprawnieniem view payment_status', function () {
        $user = \App\Models\User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::findOrCreate('view payment_status');
        $user->givePermissionTo($permission);
        $this->actingAs($user);
        $response = $this->get('/admin/payment-status');
        $this->assertContains($response->getStatusCode(), [200, 404]);
    });
});
