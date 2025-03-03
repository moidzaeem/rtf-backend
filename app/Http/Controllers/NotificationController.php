<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
    
        // Get the start of today
        $startOfToday = now()->startOfDay();
    
        // Fetch notifications for the user
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    
            $groupedNotifications = [
                'today' => $notifications->filter(function ($notification) use ($startOfToday) {
                    return $notification->created_at >= $startOfToday;
                }),
                'yesterday' => $notifications->filter(function ($notification) use ($startOfToday) {
                    return $notification->created_at < $startOfToday;
                })->values()->toArray(),
            ];
    
        return $this->sendResponse($groupedNotifications, 'User Notifications');
    }
    
    

    public function markAllAsRead(Request $request)
    {
        $userId = \Auth::id();
        // Update all notifications for the user to mark them as read
        Notification::where('user_id', $userId)->update(['is_read' => true]);
        return $this->sendResponse([], 'All notifications marked as read');
    }

}


