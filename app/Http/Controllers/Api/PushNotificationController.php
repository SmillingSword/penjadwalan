<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PusherBeamsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PushNotificationController extends Controller
{
    private $pusherBeamsService;

    public function __construct(PusherBeamsService $pusherBeamsService)
    {
        $this->pusherBeamsService = $pusherBeamsService;
    }

    /**
     * Get Pusher Beams configuration for frontend
     */
    public function getConfig(): JsonResponse
    {
        if (!$this->pusherBeamsService->isConfigured()) {
            return response()->json([
                'error' => 'Pusher Beams is not configured'
            ], 500);
        }

        return response()->json([
            'instance_id' => $this->pusherBeamsService->getInstanceId(),
            'service_worker_url' => asset('service-worker.js')
        ]);
    }

    /**
     * Generate authentication token for current user
     */
    public function getUserToken(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'error' => 'User not authenticated'
            ], 401);
        }

        $userId = (string) Auth::id();
        $token = $this->pusherBeamsService->generateUserToken($userId);

        if (!$token) {
            return response()->json([
                'error' => 'Failed to generate user token'
            ], 500);
        }

        return response()->json($token);
    }

    /**
     * Send push notification to specific users
     */
    public function sendToUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'icon' => 'nullable|string',
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $notification = [
            'title' => $request->title,
            'body' => $request->body,
            'icon' => $request->icon,
            'data' => $request->data ?? []
        ];

        $result = $this->pusherBeamsService->sendToUsers($request->user_ids, $notification);

        if (!$result) {
            return response()->json([
                'error' => 'Failed to send push notification'
            ], 500);
        }

        return response()->json([
            'message' => 'Push notification sent successfully',
            'result' => $result
        ]);
    }

    /**
     * Send push notification to interests (topics)
     */
    public function sendToInterests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'interests' => 'required|array',
            'interests.*' => 'required|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'icon' => 'nullable|string',
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $notification = [
            'title' => $request->title,
            'body' => $request->body,
            'icon' => $request->icon,
            'data' => $request->data ?? []
        ];

        $result = $this->pusherBeamsService->sendToInterests($request->interests, $notification);

        if (!$result) {
            return response()->json([
                'error' => 'Failed to send push notification'
            ], 500);
        }

        return response()->json([
            'message' => 'Push notification sent successfully',
            'result' => $result
        ]);
    }

    /**
     * Send test notification to current user
     */
    public function sendTestNotification(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'error' => 'User not authenticated'
            ], 401);
        }

        $userId = (string) Auth::id();
        $user = Auth::user();

        $notification = [
            'title' => 'Test Notification',
            'body' => "Hello {$user->name}! This is a test push notification.",
            'icon' => asset('favicon.ico'),
            'data' => [
                'type' => 'test',
                'timestamp' => now()->toISOString()
            ]
        ];

        $result = $this->pusherBeamsService->sendToUsers([$userId], $notification);

        if (!$result) {
            return response()->json([
                'error' => 'Failed to send test notification'
            ], 500);
        }

        return response()->json([
            'message' => 'Test notification sent successfully',
            'result' => $result
        ]);
    }
}
