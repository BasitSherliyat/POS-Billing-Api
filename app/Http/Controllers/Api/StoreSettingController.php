<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StoreSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = StoreSetting::query()->find(1);

        return response()->json($settings ?? [
            'id' => 1,
            'mart_name' => 'Mart Billing',
            'address' => null,
            'contact_number' => null,
            'logo_path' => null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mart_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $settings = StoreSetting::query()->find(1);
        if (! $settings) {
            $settings = new StoreSetting;
            $settings->id = 1;
        }
        $oldLogoPath = $settings->logo_path;
        $settings->mart_name = $data['mart_name'];
        $settings->address = $data['address'] ?? null;
        $settings->contact_number = $data['contact_number'] ?? null;

        if ($request->hasFile('logo')) {
            $directory = public_path('images/mart');
            File::ensureDirectoryExists($directory);
            $filename = Str::uuid().'.'.$data['logo']->extension();
            $data['logo']->move($directory, $filename);
            $settings->logo_path = '/images/mart/'.$filename;
        } elseif ($request->boolean('remove_logo')) {
            $settings->logo_path = null;
        }

        $settings->save();

        if ($settings->logo_path !== $oldLogoPath && $oldLogoPath && str_starts_with($oldLogoPath, '/images/mart/')) {
            File::delete(public_path(ltrim($oldLogoPath, '/')));
        }

        return response()->json($settings->refresh());
    }
}
