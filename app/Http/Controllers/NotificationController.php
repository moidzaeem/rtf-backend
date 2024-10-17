<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends BaseController
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return $this->sendResponse($notifications, 'User Notifications');
    }

    public function markAllAsRead(Request $request)
    {
        $userId = \Auth::id();
        // Update all notifications for the user to mark them as read
        Notification::where('user_id', $userId)->update(['is_read' => true]);
        return $this->sendResponse([], 'All notifications marked as read');
    }

}


