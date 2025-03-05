<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    use AuthorizesRequests;
    // TODO Lấy danh sách sản phẩm trong giỏ hàng
    public function index()
    {
        $cartItems = CartItem::with(['productVariation.product', 'productVariation.images'])
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($cartItems);
    }

    // TODO Thêm sản phẩm vào giỏ hàng
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_variation_id' => 'required|exists:product_variations,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = CartItem::where('user_id', Auth::id())
            ->where('product_variation_id', $request->product_variation_id)
            ->first();

        if ($cartItem) {
            // Cộng dồn số lượng nếu sản phẩm đã có trong giỏ hàng
            $cartItem->increment('quantity', $request->quantity);
        } else {
            // Tạo mới nếu chưa có sản phẩm trong giỏ hàng
            $cartItem = CartItem::create([
                'user_id' => Auth::id(),
                'product_variation_id' => $request->product_variation_id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json([
            'message' => 'Sản phẩm đã được thêm vào giỏ hàng',
            'cartItem' => $cartItem
        ]);
    }


    // TODO Cập nhật số lượng sản phẩm trong giỏ hàng
    public function updateCart(Request $request, CartItem $cartItem)
    {
        $this->authorize('update', $cartItem);

        $request->validate(['quantity' => 'required|integer|min:0']);

        if ($request->quantity == 0) {
            $cartItem->delete();
            return response()->json(['message' => 'Sản phẩm đã được xóa khỏi giỏ hàng']);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Cập nhật số lượng thành công',
            'cartItem' => $cartItem
        ]);
    }


    // TODO Xóa sản phẩm khỏi giỏ hàng
    public function removeFromCart(CartItem $cartItem)
    {
        $this->authorize('delete', $cartItem);

        $cartItem->delete();
        return response()->json(['message' => 'Xóa sản phẩm khỏi giỏ hàng thành công']);
    }

    // TODO Xóa toàn bộ giỏ hàng
    public function clearCart()
    {
        CartItem::where('user_id', Auth::id())->delete();
        return response()->json(['message' => 'Đã xóa toàn bộ giỏ hàng']);
    }
}
