<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStatistics()
    {
        $totalOrders = Order::count();
        $completedOrders = Order::where('status', 'completed')->count();
        $canceledOrders = Order::where('status', 'canceled')->count();

        // ✅ Doanh thu tổng, theo tháng, tuần => DỰA TRÊN HÓA ĐƠN ĐÃ THANH TOÁN
        $totalRevenue = Invoice::where('status', 'paid')->sum('amount');

        $monthlyRevenue = Invoice::where('status', 'paid')
            ->whereMonth('payment_date', Carbon::now()->month)
            ->whereYear('payment_date', Carbon::now()->year)
            ->sum('amount');

        $weeklyRevenue = Invoice::where('status', 'paid')
            ->whereBetween('payment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('amount');

        $totalInvoices = Invoice::count();
        $totalPaidInvoices = Invoice::where('status', 'paid')->sum('amount');

        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();

        $totalVariants = ProductVariation::count();
        $totalStock = ProductVariation::sum('stock_quantity');
        $outOfStock = ProductVariation::where('stock_quantity', 0)->count();
        $inStock = ProductVariation::where('stock_quantity', '>', 0)->count();
        $discountedProducts = ProductVariation::whereNotNull('discount_price')->count();

        $totalUsers = User::count();

        return response()->json([
            'orders' => [
                'total' => $totalOrders,
                'completed' => $completedOrders,
                'canceled' => $canceledOrders,
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'monthly' => $monthlyRevenue,
                'weekly' => $weeklyRevenue,
            ],
            'invoices' => [
                'total' => $totalInvoices,
                'total_paid' => $totalPaidInvoices,
            ],
            'products' => [
                'total' => $totalProducts,
                'active' => $activeProducts,
            ],
            'variants' => [
                'total' => $totalVariants,
                'stock_quantity' => $totalStock,
                'out_of_stock' => $outOfStock,
                'in_stock' => $inStock,
                'discounted' => $discountedProducts,
            ],
            'users' => [
                'total' => $totalUsers,
            ]
        ]);
    }
}
