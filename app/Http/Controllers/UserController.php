<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * 1) إنشاء مستخدم جديد (Admin فقط)
     */
    public function store(Request $request)
    {
        // فقط الادمن
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,publisher,user',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user
        ]);
    }

    /**
     * 2) تعديل بيانات المستخدم (User أو Publisher)
     */
    public function update(Request $request)
    {
        $user = $request->user(); // المستخدم الحالي

        $request->validate([
            'name'     => 'nullable|string|max:255',
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'theme'    => 'nullable|string|in:light,dark',
            'language' => 'nullable|string|in:en,ar',
        ]);

        if ($request->name)     $user->name     = $request->name;
        if ($request->email)    $user->email    = $request->email;
        if ($request->password) $user->password = Hash::make($request->password);
        if ($request->filled('theme'))    $user->theme    = $request->theme;
        if ($request->filled('language')) $user->language = $request->language;

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user
        ]);
    }

    /**
     * 3) عرض جميع المستخدمين (Admin فقط)
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return User::all();
    }

    /**
     * 4) حذف مستخدم (Admin فقط)
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * 5) تفعيل/إلغاء تفعيل مستخدم (Admin فقط — لا يطبَّق على المديرين)
     */
    public function toggleActive(Request $request, $id)
    {
        $target = User::findOrFail($id);

        if ($target->role === 'admin') {
            return response()->json(['message' => 'Cannot deactivate an admin account.'], 422);
        }

        $target->is_active = ! $target->is_active;
        $target->save();

        // إلغاء جميع توكنات المستخدم عند تعطيله
        if (! $target->is_active) {
            $target->tokens()->delete();
        }

        return response()->json([
            'message' => $target->is_active ? 'User activated successfully.' : 'User deactivated successfully.',
            'user'    => $target,
        ]);
    }
}
