<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestsService
{
    /**
     * Newest first. Note count and latest note come from subqueries, so the page
     * costs one count query and one select regardless of how many rows it holds.
     *
     * @return LengthAwarePaginator<int, CustomerRequest>
     */
    public function list(int $perPage): LengthAwarePaginator
    {
        $latestNoteBody = RequestNote::query()
            ->select('body')
            ->whereColumn('request_notes.request_id', 'customer_requests.id')
            ->orderByDesc('created_at')
            ->limit(1);

        return CustomerRequest::query()
            ->select('customer_requests.*')
            ->withCount('notes')
            ->addSelect(['latest_note_preview' => $latestNoteBody])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getById(string $id): CustomerRequest
    {
        $row = CustomerRequest::query()->with('notes')->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Request {$id} not found");
        }

        return $row;
    }

    public function updateStatus(string $id, string $status): CustomerRequest
    {
        $row = $this->getById($id);
        $row->status = $status;
        $row->save();

        return $row;
    }

    public function create(string $message): CustomerRequest
    {
        return CustomerRequest::create([
            'message' => $message,
            'status' => 'open',
            'category' => null,
            'confidence' => null,
        ]);
    }

    public function save(CustomerRequest $request): CustomerRequest
    {
        $request->save();

        return $request;
    }
}
