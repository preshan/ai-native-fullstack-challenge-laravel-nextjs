<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassifyRequest;
use App\Http\Requests\ListHistoryRequest;
use App\Http\Requests\ListRequestsRequest;
use App\Http\Resources\ClassificationRecordResource;
use App\Http\Resources\ClassificationResource;
use App\Http\Resources\RequestListItemResource;
use App\Services\ClassificationService;
use App\Services\RequestsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestsController extends Controller
{
    public function __construct(
        private readonly RequestsService $requestsService,
        private readonly ClassificationService $classification,
    ) {}

    public function list(ListRequestsRequest $request): AnonymousResourceCollection
    {
        return RequestListItemResource::collection(
            $this->requestsService->list($request->perPage()),
        );
    }

    public function history(ListHistoryRequest $request): AnonymousResourceCollection
    {
        return ClassificationRecordResource::collection(
            $this->classification->history($request->category(), $request->perPage()),
        );
    }

    public function show(string $id)
    {
        return $this->requestsService->getById($id);
    }

    public function store(Request $request)
    {
        $message = $request->input('message');
        if (! $message || ! is_string($message)) {
            return ['error' => 'message is required'];
        }

        return $this->requestsService->create($message);
    }

    public function updateStatus(Request $request, string $id)
    {
        $status = $request->input('status');
        if (! $status) {
            return ['error' => 'status is required'];
        }

        return $this->requestsService->updateStatus($id, $status);
    }

    public function classify(ClassifyRequest $request): ClassificationResource
    {
        $requestId = $request->requestId();

        return new ClassificationResource(
            $this->classification->classify($request->messageText(), $requestId),
            $requestId,
        );
    }
}
