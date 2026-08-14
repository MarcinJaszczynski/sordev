<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\PublicPackageDetailResource;
use App\Http\Resources\Api\V1\PublicPackageResource;
use App\Http\Resources\Api\V1\RegionResource;
use App\Services\PublicPackageCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends BaseApiController
{
    public function __construct(
        private readonly PublicPackageCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'start_place_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->catalog->paginate($validated);

        return $this->success([
            'items' => PublicPackageResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(string $package): JsonResponse
    {
        $template = $this->catalog->findByIdOrSlug($package);

        if (! $template) {
            return $this->error('Oferta nie znaleziona.', [], 404);
        }

        return $this->success(PublicPackageDetailResource::make($template)->resolve());
    }

    public function regions(): JsonResponse
    {
        return $this->success(
            RegionResource::collection($this->catalog->startingPlaces())->resolve()
        );
    }
}
