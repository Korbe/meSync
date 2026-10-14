<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_lists_own_customers(): void
    {
        $user = User::factory()->create();
        Customer::factory()->count(2)->for($user)->create();
        Customer::factory()->create();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers.data', 2));
    }

    public function test_index_can_be_searched(): void
    {
        $user = User::factory()->create();
        Customer::factory()->for($user)->create(['last_name' => 'Mustermann']);
        Customer::factory()->for($user)->create(['last_name' => 'Huber', 'email' => 'huber@example.com']);

        $this->actingAs($user)
            ->get(route('customers.index', ['search' => 'muster']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.last_name', 'Mustermann'));
    }

    public function test_customer_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('customers.store'), [
                'title_prefix' => 'Dr.',
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'title_suffix' => 'MSc',
                'company_name' => 'Musterfirma',
                'company_suffix' => 'GmbH',
                'street' => 'Hauptplatz 1',
                'zip' => '9500',
                'city' => 'Villach',
                'country' => 'Österreich',
                'email' => 'max@musterfirma.at',
                'phone' => '+43 664 1234567',
                'uid_number' => 'atu 123 456 78',
                'notes' => 'Stammkunde',
            ])
            ->assertRedirect(route('customers.index'));

        $customer = $user->customers()->sole();

        $this->assertSame('ATU12345678', $customer->uid_number);
        $this->assertSame('Dr. Max Mustermann, MSc', $customer->full_name);
        $this->assertSame('Musterfirma GmbH', $customer->company);
    }

    public function test_company_only_customer_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('customers.store'), ['company_name' => 'Musterfirma'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Musterfirma', $user->customers()->sole()->display_name);
    }

    public function test_last_name_or_company_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('customers.store'), ['first_name' => 'Max'])
            ->assertSessionHasErrors(['last_name', 'company_name']);
    }

    public function test_invalid_uid_number_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('customers.store'), ['last_name' => 'Mustermann', 'uid_number' => '12345'])
            ->assertSessionHasErrors('uid_number');
    }

    public function test_customer_can_be_updated(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)
            ->put(route('customers.update', $customer), ['last_name' => 'Neu', 'city' => 'Graz'])
            ->assertRedirect(route('customers.index'));

        $this->assertSame('Graz', $customer->fresh()->city);
    }

    public function test_customer_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('customers.delete', $customer))
            ->assertInertia(fn (Assert $page) => $page->component('Customers/Delete'));

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertModelMissing($customer);
    }

    public function test_customers_of_other_users_are_forbidden(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->get(route('customers.edit', $customer))->assertForbidden();
        $this->actingAs($user)->put(route('customers.update', $customer), ['last_name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get(route('customers.delete', $customer))->assertForbidden();
        $this->actingAs($user)->delete(route('customers.destroy', $customer))->assertForbidden();

        $this->assertModelExists($customer);
    }
}
