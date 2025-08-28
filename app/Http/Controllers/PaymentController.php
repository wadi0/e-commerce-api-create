<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function initPayment(Request $request)
    {
        $post_data = [
            'store_id' => env('SSLCZ_STORE_ID'),
            'store_passwd' => env('SSLCZ_STORE_PASS'),
            'total_amount' => $request->amount,
            'currency' => "BDT",
            'tran_id' => uniqid(),
            'success_url' => url('/api/payment/success'),
            'fail_url' => url('/api/payment/fail'),
            'cancel_url' => url('/api/payment/cancel'),
            'cus_name' => $request->name,
            'cus_email' => $request->email,
            'cus_add1' => $request->address,
            'cus_phone' => $request->phone,
        ];

        $url = env('SSLCZ_SANDBOX')
            ? "https://sandbox.sslcommerz.com/gwprocess/v3/api.php"
            : "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

        $response = Http::asForm()->post($url, $post_data);

        if ($response->successful() && isset($response['GatewayPageURL'])) {
            return response()->json([
                'status' => 'success',
                'redirect_url' => $response['GatewayPageURL']
            ]);
        } else {
            return response()->json(['status' => 'fail', 'message' => 'SSLCommerz init failed']);
        }
    }

    public function success(Request $request)
    {
        // এখানে তুমি order status update করতে পারো
        return response()->json(['status' => 'Payment Success', 'data' => $request->all()]);
    }

    public function fail(Request $request)
    {
        return response()->json(['status' => 'Payment Failed']);
    }

    public function cancel(Request $request)
    {
        return response()->json(['status' => 'Payment Cancelled']);
    }
}
