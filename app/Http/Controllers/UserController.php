<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Cập nhật thông tin người dùng.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // Kiểm tra dữ liệu đầu vào
        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        // Cập nhật thông tin
        if ($request->filled('name')) {
            $user->name = $request->input('name');
        }
        if ($request->filled('phone')) {
            $user->phone = $request->input('phone');
        }
        if ($request->filled('address')) {
            $user->address = $request->input('address');
        }

        // Lưu thông tin
        $user->save();

        return response()->json([
            'message' => 'Cập nhật thông tin thành công.',
            'user' => $user
        ]);
    }

    /**
     * Đổi mật khẩu người dùng.
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        Log::info('changePassword');

        // Tạo Validator
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Kiểm tra nếu validation thất bại
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Kiểm tra mật khẩu hiện tại
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng.',
            ], 400);
        }

        // Cập nhật mật khẩu mới
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }
    public function getListUser()
    {
        $users = User::where('role', 'customer')->get();
        return response()->json($users, Response::HTTP_OK);
    }
}
