<?php

namespace Database\Seeders;

use App\Modules\SuppliersAndProcurement\Enums\GeoPlaceKind;
use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeoPlacesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $sa = GeoPlace::query()->updateOrCreate(
                [
                    'country_code' => 'SA',
                    'kind' => GeoPlaceKind::Country->value,
                    'code' => 'SA',
                ],
                [
                    'name_ar' => 'المملكة العربية السعودية',
                    'name_en' => 'Saudi Arabia',
                    'parent_id' => null,
                    'is_active' => true,
                ],
            );

            $children = [
                ['code' => 'MAKKAH', 'kind' => GeoPlaceKind::City, 'name_ar' => 'مكة المكرمة', 'name_en' => 'Makkah', 'parent' => $sa],
                ['code' => 'MADINAH', 'kind' => GeoPlaceKind::City, 'name_ar' => 'المدينة المنورة', 'name_en' => 'Madinah', 'parent' => $sa],
                ['code' => 'JEDDAH', 'kind' => GeoPlaceKind::City, 'name_ar' => 'جدة', 'name_en' => 'Jeddah', 'parent' => $sa],
            ];

            $makkah = null;
            foreach ($children as $row) {
                /** @var GeoPlace $place */
                $place = GeoPlace::query()->updateOrCreate(
                    [
                        'country_code' => 'SA',
                        'kind' => $row['kind']->value,
                        'code' => $row['code'],
                    ],
                    [
                        'name_ar' => $row['name_ar'],
                        'name_en' => $row['name_en'],
                        'parent_id' => $row['parent']->id,
                        'is_active' => true,
                    ],
                );
                if ($row['code'] === 'MAKKAH') {
                    $makkah = $place;
                }
            }

            if ($makkah === null) {
                throw new \RuntimeException('MAKKAH geo place seed failed.');
            }

            $holySites = [
                ['code' => 'MINA', 'name_ar' => 'منى', 'name_en' => 'Mina'],
                ['code' => 'ARAFAT', 'name_ar' => 'عرفات', 'name_en' => 'Arafat'],
                ['code' => 'MUZDALIFAH', 'name_ar' => 'مزدلفة', 'name_en' => 'Muzdalifah'],
            ];

            foreach ($holySites as $site) {
                GeoPlace::query()->updateOrCreate(
                    [
                        'country_code' => 'SA',
                        'kind' => GeoPlaceKind::HolySite->value,
                        'code' => $site['code'],
                    ],
                    [
                        'name_ar' => $site['name_ar'],
                        'name_en' => $site['name_en'],
                        'parent_id' => $makkah->id,
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
