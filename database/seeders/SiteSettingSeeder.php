<?php
namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'announcement_active' => '0',
            'announcement_text' => 'Gratis ongkir untuk pembelian pertama!',
            'announcement_link_url' => '/search',
            'announcement_link_label' => 'Belanja Sekarang',
        ];
        foreach ($defaults as $k => $v) {
            SiteSetting::firstOrCreate(['key' => $k], ['value' => $v]);
        }
    }
}
