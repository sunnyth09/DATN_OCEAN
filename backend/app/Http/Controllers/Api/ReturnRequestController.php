<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReturnRequestRequest;
use App\Http\Requests\UpdateReturnRequestStatusRequest;
use App\Services\ReturnRequestService;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    public function __construct(
        protected ReturnRequestService $returnRequestService
    ) {}

    public function store(StoreReturnRequestRequest $request, int $order)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để gửi yêu cầu hoàn hàng.',
            ], 401);
        }

        $result = $this->returnRequestService->create(
            $user->user_id,
            $order,
            $request->validated(),
            $request
        );

        return response()->json($result['body'], $result['status_code']);
    }

    public function myIndex(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $result = $this->returnRequestService->getMyRequests($user->user_id, $request->only(['status']));

        return response()->json($result['body'], $result['status_code']);
    }

    public function myShow(int $id)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $result = $this->returnRequestService->getMyRequestDetail($user->user_id, $id);

        return response()->json($result['body'], $result['status_code']);
    }

    public function adminIndex(Request $request)
    {
        $result = $this->returnRequestService->getAdminRequests(
            $request->only(['status', 'refund_status', 'search'])
        );

        return response()->json($result['body'], $result['status_code']);
    }

    public function adminShow(int $id)
    {
        $result = $this->returnRequestService->getAdminRequestDetail($id);

        return response()->json($result['body'], $result['status_code']);
    }

    public function approve(UpdateReturnRequestStatusRequest $request, int $id)
    {
        $result = $this->returnRequestService->approve($id, $request->validated());

        return response()->json($result['body'], $result['status_code']);
    }

    public function reject(UpdateReturnRequestStatusRequest $request, int $id)
    {
        $result = $this->returnRequestService->reject($id, $request->validated());

        return response()->json($result['body'], $result['status_code']);
    }

    public function returning(UpdateReturnRequestStatusRequest $request, int $id)
    {
        $result = $this->returnRequestService->markReturning($id, $request->validated());

        return response()->json($result['body'], $result['status_code']);
    }

    public function received(UpdateReturnRequestStatusRequest $request, int $id)
    {
        $result = $this->returnRequestService->markReceived($id, $request->validated());

        return response()->json($result['body'], $result['status_code']);
    }

    public function inspect(UpdateReturnRequestStatusRequest $request, int $id)
    {
        $result = $this->returnRequestService->inspect($id, $request->validated());

        return response()->json($result['body'], $result['status_code']);
    }

    public function refund(UpdateReturnRequestStatusRequest $request, int $id)
    {
        $result = $this->returnRequestService->refund($id, $request->validated());

        return response()->json($result['body'], $result['status_code']);
    }
}
