<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function brands(): JsonResponse
    {
        return response()->json(CarBrand::orderBy('name')->get(['id', 'name']));
    }

    public function models(Request $request): JsonResponse
    {
        return response()->json(
            CarModel::where('car_brand_id', $request->brand_id)
                ->orderBy('name')->get(['id', 'name', 'car_brand_id'])
        );
    }

    public function versions(Request $request): JsonResponse
    {
        return response()->json(
            CarVersion::where('car_model_id', $request->model_id)
                ->orderBy('name')->get(['id', 'name', 'car_model_id'])
        );
    }
}
