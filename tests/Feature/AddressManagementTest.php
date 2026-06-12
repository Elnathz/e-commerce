<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressManagementTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;
    private City $city;
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->province = Province::create(['name' => 'Jawa Tengah']);
        $this->city = City::create([
            'province_id' => $this->province->id,
            'name' => 'Semarang',
            'type' => 'Kota',
            'postal_code' => '50132',
        ]);
        $this->district = District::create([
            'city_id' => $this->city->id,
            'name' => 'Tembalang',
        ]);
    }

    public function test_profile_page_includes_only_the_authenticated_users_addresses()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = UserAddress::create([
            'user_id' => $user->id,
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Mangga No. 1',
            'is_default' => true,
        ]);

        UserAddress::create([
            'user_id' => $other->id,
            'label' => 'Rumah',
            'recipient_name' => 'Other',
            'phone' => '081200000000',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Lain No. 2',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $addresses = $response->viewData('page')['props']['addresses'];
        $addressIds = array_column($addresses, 'id');

        $this->assertContains($own->id, $addressIds);
        $this->assertCount(1, $addressIds);

        // The edit form needs the province id to pre-populate the cascading
        // province > city > district selects.
        $this->assertSame($this->province->id, $addresses[0]['city_relation']['province']['id']);
    }

    public function test_first_address_is_forced_to_default_regardless_of_input()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('addresses.store'), [
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'address_detail' => 'Jl. Mangga No. 1',
            'is_default' => false,
        ]);

        $response->assertRedirect();

        $address = UserAddress::where('user_id', $user->id)->first();
        $this->assertNotNull($address);
        $this->assertEquals(1, $address->is_default);
        $this->assertSame($this->province->name, $address->province);
        $this->assertSame($this->city->id, $address->city_id);
        $this->assertSame($this->district->id, $address->district_id);
        $this->assertSame('50132', $address->postal_code);
    }

    public function test_setting_a_new_default_address_unsets_the_previous_default()
    {
        $user = User::factory()->create();

        $first = UserAddress::create([
            'user_id' => $user->id,
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Mangga No. 1',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->post(route('addresses.store'), [
            'label' => 'Kantor',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'address_detail' => 'Jl. Kantor No. 2',
            'is_default' => true,
        ]);

        $response->assertRedirect();

        $this->assertEquals(0, $first->refresh()->is_default);
        $second = UserAddress::where('user_id', $user->id)->where('label', 'Kantor')->first();
        $this->assertEquals(1, $second->is_default);
    }

    public function test_user_cannot_update_another_users_address()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $address = UserAddress::create([
            'user_id' => $owner->id,
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Mangga No. 1',
            'is_default' => true,
        ]);

        $response = $this->actingAs($intruder)->put(route('addresses.update', $address->id), [
            'label' => 'Kantor',
            'recipient_name' => 'Hacker',
            'phone' => '089999999999',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'address_detail' => 'Jl. Hack No. 1',
            'is_default' => true,
        ]);

        $response->assertForbidden();
        $this->assertSame('Budi', $address->refresh()->recipient_name);
    }

    public function test_deleting_default_address_promotes_another_to_default()
    {
        $user = User::factory()->create();

        $default = UserAddress::create([
            'user_id' => $user->id,
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Mangga No. 1',
            'is_default' => true,
        ]);

        $secondary = UserAddress::create([
            'user_id' => $user->id,
            'label' => 'Kantor',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Kantor No. 2',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)->delete(route('addresses.destroy', $default->id));

        $response->assertRedirect();
        $this->assertNull(UserAddress::find($default->id));
        $this->assertEquals(1, $secondary->refresh()->is_default);
    }

    public function test_user_cannot_delete_or_set_default_on_another_users_address()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $address = UserAddress::create([
            'user_id' => $owner->id,
            'label' => 'Rumah',
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'province' => $this->province->name,
            'city' => $this->city->name,
            'city_id' => $this->city->id,
            'district' => $this->district->name,
            'district_id' => $this->district->id,
            'postal_code' => '50132',
            'address_detail' => 'Jl. Mangga No. 1',
            'is_default' => true,
        ]);

        $this->actingAs($intruder)->delete(route('addresses.destroy', $address->id))->assertForbidden();
        $this->actingAs($intruder)->patch(route('addresses.setDefault', $address->id))->assertForbidden();

        $this->assertNotNull(UserAddress::find($address->id));
        $this->assertEquals(1, $address->refresh()->is_default);
    }
}
