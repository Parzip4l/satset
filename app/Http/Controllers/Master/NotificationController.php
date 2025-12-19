<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Master\Notification;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->take(10)
            ->get();

        $unreadCount = Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead($id, Request $request)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->is_read = true;
        $notification->save();

        Log::info('Marking notification as read', [
            'notification_id' => $id,
            'auth_user' => auth()->id(),
            'found' => $notification ? true : false
        ]);

        return response()->json(['success' => true]);
    }


    public function clearAll()
    {
        Notification::where('user_id', Auth::id())->delete();
        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }
    
}
