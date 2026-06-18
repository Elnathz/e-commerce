<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'announcement_active' => SiteSetting::get('announcement_active') === '1',
                'announcement_text' => SiteSetting::get('announcement_text'),
                'announcement_link_url' => SiteSetting::get('announcement_link_url'),
                'announcement_link_label' => SiteSetting::get('announcement_link_label'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $v = $request->validate([
            'announcement_active' => 'boolean',
            'announcement_text' => 'nullable|string|max:255',
            'announcement_link_url' => 'nullable|string|max:500',
            'announcement_link_label' => 'nullable|string|max:100',
        ]);
        SiteSetting::set('announcement_active', $request->boolean('announcement_active') ? '1' : '0');
        SiteSetting::set('announcement_text', $v['announcement_text'] ?? '');
        SiteSetting::set('announcement_link_url', $v['announcement_link_url'] ?? '');
        SiteSetting::set('announcement_link_label', $v['announcement_link_label'] ?? '');
        return back()->with('success', 'Pengaturan disimpan.');
    }
}
