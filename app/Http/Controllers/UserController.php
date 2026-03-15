<?php

namespace App\Http\Controllers;

use App\Contracts\Services\MessageServiceInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
    ) {}

    /**
     * Display a listing of all registered users except the current user.
     */
    public function index(Request $request): View
    {
        $authUserId = $request->user()->id;

        $users = User::where('id', '!=', $authUserId)
            ->orderBy('name')
            ->get();

        $unreadCounts = $this->messageService->getUnreadCountsBySender($authUserId);

        return view('users.index', compact('users', 'unreadCounts'));
    }
}
