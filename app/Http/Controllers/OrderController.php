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
                $price = $item->productVariation['discount_price'] ?? $item->productVariation['price'];
                return $price * $item['quantity'];
            });

            // Thêm phí vận chuyển
            $shippingFee = 35000;
            $totalAmount += $shippingFee;

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
                'shipping_fee' => $shippingFee,
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
            ->with(['items.variation.product', 'items.variation.images', 'items.variation.attributes', 'items.variation.attributes.attributeValue'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function getAllOrders()
    {
        $orders = Order::with(['user', 'items.variation.product', 'items.variation.images', 'items.variation.attributes', 'items.variation.attributes.attributeValue'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    // Mới thêm: Chức năng tìm kiếm đơn hàng
    public function searchOrders(Request $request)
    {
        // Validate search parameters
        $request->validate([
            'keyword' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,processing,shipped,delivered,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'order_number' => 'nullable|string',
        ]);

        // Base query
        $query = Order::query()->with(['user', 'items.variation.product']);

        // Apply filters based on search parameters
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('shipping_name', 'like', "%{$keyword}%")
                    ->orWhere('shipping_phone', 'like', "%{$keyword}%")
                    ->orWhere('shipping_address', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {
                        $userQuery->where('name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', "%{$request->order_number}%");
        }

        // Get paginated results
        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json(['orders' => $orders]);
    }

    // Mới thêm: Chức năng xóa đơn hàng (chỉ admin)
    public function deleteOrder($orderId)
    {
        // Tìm đơn hàng theo ID
        $order = Order::findOrFail($orderId);

        DB::beginTransaction();
        try {
            // Nếu đơn hàng chưa giao, hoàn lại tồn kho
            if (!in_array($order->status, ['delivered'])) {
                foreach ($order->items as $item) {
                    $productVariation = $item->variation;
                    $productVariation->increment('stock_quantity', $item->quantity);
                }
            }

            // Xóa các chi tiết đơn hàng
            OrderItem::where('order_id', $orderId)->delete();

            // Xóa đơn hàng
            $order->delete();

            DB::commit();
            return response()->json(['message' => 'Đơn hàng đã được xóa thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi khi xóa đơn hàng', 'error' => $e->getMessage()], 500);
        }
    }

    // Mới thêm: Chức năng sửa thông tin đơn hàng (chỉ admin)
    public function updateOrder(Request $request, $orderId)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'shipping_address' => 'nullable|string|max:255',
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string',
            'payment_status' => 'nullable|string|in:pending,paid,refunded',
            'notes' => 'nullable|string',
        ]);

        // Tìm đơn hàng theo ID
        $order = Order::findOrFail($orderId);

        try {
            // Cập nhật thông tin đơn hàng
            $updateData = array_filter($request->only([
                'shipping_address',
                'shipping_phone',
                'shipping_name',
                'payment_method',
                'payment_status',
                'notes'
            ]));

            $order->update($updateData);

            return response()->json([
                'message' => 'Cập nhật đơn hàng thành công',
                'order' => $order->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi cập nhật đơn hàng', 'error' => $e->getMessage()], 500);
        }
    }

    // Mới thêm: Chức năng chuyển trạng thái đơn hàng (chỉ admin)
    public function updateOrderStatus(Request $request, $orderId)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
            'notes' => 'nullable|string',
        ]);

        // Tìm đơn hàng theo ID
        $order = Order::findOrFail($orderId);

        // Kiểm tra trạng thái hợp lệ theo quy trình
        $validTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => []
        ];

        if (!in_array($request->status, $validTransitions[$order->status])) {
            return response()->json([
                'message' => 'Không thể chuyển trạng thái từ ' .
                    $this->translateStatus($order->status) .
                    ' sang ' . $this->translateStatus($request->status)
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Cập nhật trạng thái đơn hàng
            $updateData = [
                'status' => $request->status
            ];

            if ($request->filled('notes')) {
                $updateData['notes'] = $request->notes;
            }

            // Nếu đơn hàng hủy và đã thanh toán, cập nhật trạng thái thanh toán thành hoàn tiền
            if ($request->status === 'cancelled' && $order->payment_status === 'paid') {
                $updateData['payment_status'] = 'refunded';
            }

            // Nếu hủy đơn hàng, hoàn lại tồn kho
            if ($request->status === 'cancelled' && $order->status !== 'cancelled') {
                foreach ($order->items as $item) {
                    $productVariation = $item->variation;
                    $productVariation->increment('stock_quantity', $item->quantity);
                }
            }

            $order->update($updateData);

            DB::commit();
            return response()->json([
                'message' => 'Cập nhật trạng thái đơn hàng thành công',
                'order' => $order->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi khi cập nhật trạng thái đơn hàng', 'error' => $e->getMessage()], 500);
        }
    }

    // Mới thêm: Hàm dịch trạng thái sang tiếng Việt để hiển thị thông báo
    private function translateStatus($status)
    {
        $translations = [
            'pending' => 'Đang chờ xác nhận',
            'processing' => 'Đang chờ vận chuyển',
            'shipped' => 'Đã vận chuyển',
            'delivered' => 'Đã giao thành công',
            'cancelled' => 'Đã hủy'
        ];

        return $translations[$status] ?? $status;
    }

    // Mới thêm: Xem chi tiết đơn hàng
    public function getOrderDetail($orderId)
    {
        $order = Order::with([
            'user',
            'items.variation.product',
            'items.variation.images',
            'items.variation.attributes',
            'items.variation.attributes.attributeValue'
        ])->findOrFail($orderId);

        return response()->json(['order' => $order]);
    }
}
