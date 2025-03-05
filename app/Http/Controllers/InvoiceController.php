<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Str;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    
    //TODO Tạo hóa đơn mới cho đơn hàng
    public function createInvoice($orderId)
    {
        $user = Auth::user();

        // TODO Tìm đơn hàng của chính user đang đăng nhập
        $order = Order::where('id', $orderId)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // TODO Kiểm tra nếu đơn hàng đã có hóa đơn
        if ($order->invoice) {
            return response()->json(['message' => 'Hóa đơn đã tồn tại'], 400);
        }

        // TODO Tạo hóa đơn
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => Str::uuid(),
            'amount' => $order->total_amount,
            'status' => 'unpaid',
        ]);

        return response()->json(['message' => 'Hóa đơn đã được tạo', 'invoice' => $invoice]);
    }


    //TODO Xử lý thanh toán với VNPAY
    public function payWithVnpay($invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $vnp_TmnCode = env('VNP_TMN_CODE');
        $vnp_HashSecret = env('VNP_HASH_SECRET');
        $vnp_Url = env('VNP_URL');
        $vnp_Returnurl = env('VNP_RETURN_URL');

        $vnp_TxnRef = $invoice->invoice_number;
        $vnp_OrderInfo = "Thanh toán hóa đơn " . $invoice->invoice_number;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $invoice->amount * 100;
        $vnp_Locale = 'vn';
        $vnp_BankCode = '';

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => now()->format('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => request()->ip(),
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        return response()->json(['redirect_url' => $vnp_Url]);
    }
    //TODO Xử lý kết quả thanh toán từ VNPAY
    public function vnpayReturn(Request $request)
    {
        $clientUrl = env('DOMAIN_CLIENT', 'http://localhost:3000');
        $invoice = Invoice::where('invoice_number', $request->vnp_TxnRef)->first();

        if (!$invoice) {
            return redirect()->to("$clientUrl/check-payment-status?status=error&message=Hóa đơn không tồn tại");
        }

        if ($request->vnp_ResponseCode == '00') {
            // TODO Cập nhật hóa đơn
            $invoice->update([
                'status' => 'paid',
                'payment_date' => now(),
            ]);

            // TODO Cập nhật đơn hàng
            $order = Order::where('id', $invoice->order_id)->first();
            if ($order) {
                $order->update(['payment_status' => 'paid']);
            }

            // TODO Redirect về FE
            return redirect()->to("$clientUrl/check-payment-status?invoice_number=" . $invoice->invoice_number);
        } else {
            // TODO Cập nhật trạng thái thất bại
            $invoice->update(['status' => 'failed']);

            $order = Order::where('id', $invoice->order_id)->first();
            if ($order) {
                $order->update(['payment_status' => 'failed']);
            }

            return redirect()->to("$clientUrl/check-payment-status?invoice_number=" . $invoice->invoice_number);
        }
    }

    public function getPaymentStatus(Request $request)
    {
        $invoice = Invoice::where('invoice_number', $request->invoice_number)->first();

        if (!$invoice) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hóa đơn không tồn tại'
            ], 404);
        }

        return response()->json([
            'status' => $invoice->status === 'paid' ? 'success' : ($invoice->status === 'failed' ? 'failed' : 'pending'),
            'message' => $invoice->status === 'paid' ? 'Thanh toán thành công' : ($invoice->status === 'failed' ? 'Thanh toán thất bại' : 'Chưa thanh toán')
        ]);
    }


}
