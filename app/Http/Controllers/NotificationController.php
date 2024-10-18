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
        
        // Get the start and end of the time range
        $startOfToday = now()->startOfDay();
        $startOfYesterday = now()->subDay()->startOfDay();
        $endOfYesterday = now()->subDay()->endOfDay();
        
        // Fetch notifications from today and yesterday
        $notifications = Notification::where('user_id', $userId)
            ->where(function ($query) use ($startOfToday, $startOfYesterday, $endOfYesterday) {
                $query->where('created_at', '>=', $startOfYesterday)
                      ->where('created_at', '<=', $startOfToday);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Group notifications by date
        $groupedNotifications = [
            'today' => $notifications->filter(function ($notification) use ($startOfToday) {
                return $notification->created_at >= $startOfToday;
            }),
            'yesterday' => $notifications->filter(function ($notification) use ($startOfYesterday, $endOfYesterday) {
                return $notification->created_at >= $startOfYesterday && $notification->created_at <= $endOfYesterday;
            }),
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


