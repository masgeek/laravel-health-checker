<?php

namespace Masgeek\HealthCheck\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Masgeek\HealthCheck\Services\HealthCheckService;

class HealthCheckController extends Controller
{
    public function __construct(private readonly HealthCheckService $service)
    {
    }
    public function __invoke(): JsonResponse
    {
        $result = $this->service->run();

        if (!config('healthcheck.expose_details', false)) {
            $result['checks'] = collect($result['checks'])
                ->map(fn (array $check) => ['status' => $check['status'] ?? 'DOWN'])
                ->all();
        }

        $status = $result['status'] === 'healthy' ? 200 : 500;

        return response()->json($result, $status);
    }
}
