<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\City;
use Illuminate\Http\Request;

class GeographyController extends Controller
{
    public function regions(Request $request)
    {
        // For now we only have Chile, but we could filter by country_id if needed
        $regions = Region::orderBy('name')->get();
        return response()->json($regions);
    }

    public function cities(Request $request)
    {
        $request->validate([
            'region_id' => 'nullable|exists:regions,id'
        ]);

        $query = City::orderBy('name');

        if ($request->region_id) {
            $query->where('region_id', $request->region_id);
        }

        $cities = $query->get();
            
        return response()->json($cities);
    }
}
