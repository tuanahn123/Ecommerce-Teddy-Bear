<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function createOrder(Request $request)
    {
        $user = Auth::user();

        //TODO Validate request
        $request->validate([
            'cart_items' => 'required|array|min:1',
            'cart_items.*' => 'exists:cart_items,id',
            'shipping_address' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_name' => 'required|string|max:255',
            'payment_method' => 'required|string',
        ]);

        //TODO Lấy các sản phẩm giỏ hàng theo danh sách ID được gửi lên
        $cartItems = CartItem::where('user_id', $user->id)
            ->whereIn('id', $request->cart_items)->with(['productVariation.product', 'productVariation.images', 'productVariation.attributes'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Không có sản phẩm hợp lệ để đặt hàng'], 400);
        }

        DB::beginTransaction();
        try {
            //TODO Tính tổng tiền đơn hàng
            $totalAmount = $cartItems->sum(function ($item) {
                return $item->productVariation['price'] * $item['quantity'];
            });

            //TODO Tạo đơn hàng mới
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => Str::uuid(),
                'total_amount' => $totalAmount,
                'shipping_address' => $request->shipping_address,
                'shipping_phone' => $request->shipping_phone,
                'shipping_name' => $request->shipping_name,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            //TODO Thêm các sản phẩm đã chọn vào đơn hàng
            foreach ($cartItems as $cartItem) {
                $productVariation = $cartItem->productVariation;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productVariation->product->id,
                    'product_variation_id' => $cartItem->product_variation_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $productVariation->price,
                    'discount_price' => $productVariation->discount_price,
                    'attributes_snapshot' => json_encode($productVariation->attributes),
                ]);
                // TODO Trừ tồn kho
                $productVariation->decrement('stock_quantity', $cartItem->quantity);
            }

            //TODO Xóa các sản phẩm đã đặt khỏi giỏ hàng
            CartItem::whereIn('id', $request->cart_items)->delete();

            DB::commit();
            return response()->json([
                'message' => 'Đặt hàng thành công',
                'order' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e->getTraceAsString());
            return response()->json(['message' => 'Có lỗi xảy ra', 'error' => $e->getMessage()], 500);
        }
    }

    public function cancelOrder(Request $request, $orderId)
    {
        $user = Auth::user();

        //TODO Xác thực dữ liệu đầu vào
        $request->validate([
            'cancellation_reason' => 'required|string|max:255',
        ]);

        //TODO Tìm đơn hàng của người dùng
        $order = Order::where('id', $orderId)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        //TODO Kiểm tra trạng thái đơn hàng (chỉ có thể hủy nếu chưa giao)
        if (in_array($order->status, ['shipped', 'delivered'])) {
            return response()->json(['message' => 'Không thể hủy đơn hàng đã vận chuyển'], 400);
        }

        DB::beginTransaction();
        try {
            //TODO Cập nhật trạng thái đơn hàng thành "cancelled"
            $order->update([
                'status' => 'cancelled',
                'payment_status' => $order->payment_status === 'paid' ? 'refunded' : 'pending',
                'notes' => $request->cancellation_reason,
            ]);

            //TODO Hoàn kho
            foreach ($order->items as $item) {
                $productVariation = $item->variation;
                $productVariation->increment('stock_quantity', $item->quantity);
            }

            DB::commit();
            return response()->json(['message' => 'Đơn hàng đã bị hủy thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi khi hủy đơn hàng', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserOrders()
    {
        $user = Auth::user();

        //TODO Lấy danh sách đơn hàng của người dùng, sắp xếp theo thời gian mới nhất
        $orders = Order::where('user_id', $user->id)
            ->with(['items.variation.product', 'items.variation.images','items.variation.attributes','items.variation.attributes.attributeValue'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function getAllOrders()
    {
        $orders = Order::with(['user', 'items.variation.product', 'items.variation.images','items.variation.attributes','items.variation.attributes.attributeValue'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }
}
