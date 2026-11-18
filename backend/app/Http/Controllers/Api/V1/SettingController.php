<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use App\Http\Resources\PublicSettingResource;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('view', Setting::class);

        return response()->json([
            'data' => new SettingResource($this->settingsService->current()),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $setting = $this->settingsService->update($request->validated());

        return response()->json([
            'message' => 'Definições atualizadas com sucesso.',
            'data' => new SettingResource($setting),
        ]);
    }

    public function publicShow(): JsonResponse
    {
        return response()->json([
            'data' => new PublicSettingResource($this->settingsService->publicPayload()),
        ]);
    }
}
