<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationCenter $notifications): JsonResponse
    {
        return response()->json($notifications->payload($request));
    }

    public function markRead(Request $request, NotificationCenter $notifications): JsonResponse
    {
        $data = $request->validate([
            'keys' => ['required', 'array'],
            'keys.*' => ['string', 'max:255'],
        ]);

        $notifications->markRead($request, $data['keys']);

        return response()->json(['ok' => true]);
    }
}
