<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('q');

        $users = User::query()
            ->select('id', 'full_name', 'email', 'username', 'role',
                     'status', 'auth_provider', 'karma_points',
                     'email_verified_at', 'last_login_at', 'created_at')
            ->when($q, fn($query) => $query
                ->where('email',     'like', "%$q%")
                ->orWhere('full_name','like', "%$q%")
                ->orWhere('username', 'like', "%$q%")
            )
            ->when($request->input('role'),   fn($query) => $query->where('role',   $request->role))
            ->when($request->input('status'), fn($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role'     => 'sometimes|in:reader,contributor,moderator,admin',
            'status'   => 'sometimes|in:active,suspended,banned,pending',
            'full_name'=> 'sometimes|string|max:150',
        ]);

        $user->update($data);

        return response()->json(['message' => 'تم تحديث المستخدم', 'user' => $user]);
    }

    public function destroy(User $user)
    {
        // Ne pas supprimer les admins via l'API
        if ($user->role === 'admin') {
            return response()->json(['message' => 'لا يمكن حذف مدير النظام'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'تم حذف المستخدم']);
    }

    public function stats()
    {
        return response()->json([
            'total'       => User::count(),
            'active'      => User::where('status', 'active')->count(),
            'suspended'   => User::where('status', 'suspended')->count(),
            'by_role'     => User::selectRaw('role, COUNT(*) as count')->groupBy('role')->pluck('count', 'role'),
            'new_today'   => User::whereDate('created_at', today())->count(),
            'new_week'    => User::where('created_at', '>=', now()->subWeek())->count(),
        ]);
    }
}
