<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function initPayment(Request $request)
    {
        // ✅ Use credentials from .env file
        $storeId = env('SSLCZ_STORE_ID');
        $storePassword = env('SSLCZ_STORE_PASS');

        // ✅ Round amount to 2 decimal places
        $amount = round($request->amount, 2);

        // ✅ Generate unique transaction ID
        $tranId = 'TXN_' . time() . '_' . rand(1000, 9999);

        $post_data = [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'success_url' => 'https://zaw-collection-laravel-api-admin-fr.vercel.app/payment/success',
            'fail_url' => 'https://zaw-collection-laravel-api-admin-fr.vercel.app/payment/fail',
            'cancel_url' => 'https://zaw-collection-laravel-api-admin-fr.vercel.app/payment/cancel',
            'ipn_url' => url('/api/payment/ipn'),

            // ✅ Customer Information
            'cus_name' => substr($request->name, 0, 50),
            'cus_email' => $request->email,
            'cus_add1' => substr($request->address, 0, 200),
            'cus_add2' => '',
            'cus_city' => 'Dhaka',
            'cus_state' => 'Dhaka',
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $request->phone,
            'cus_fax' => '',

            // ✅ Shipment Information
            'ship_name' => substr($request->name, 0, 50),
            'ship_add1' => substr($request->address, 0, 200),
            'ship_add2' => '',
            'ship_city' => 'Dhaka',
            'ship_state' => 'Dhaka',
            'ship_postcode' => '1000',
            'ship_country' => 'Bangladesh',

            // ✅ Product Information
            'product_name' => 'E-commerce Order',
            'product_category' => 'General',
            'product_profile' => 'general',

            // ✅ Additional fields
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'multi_card_name' => '',
            'value_a' => '',
            'value_b' => '',
            'value_c' => '',
            'value_d' => '',
        ];

        // ✅ Use sandbox/live URL based on env
        $url = env('SSLCZ_SANDBOX')
            ? "https://sandbox.sslcommerz.com/gwprocess/v3/api.php"
            : "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

        Log::info('SSLCommerz Payment Request:', [
            'url' => $url,
            'store_id' => $storeId,
            'amount' => $amount,
            'tran_id' => $tranId
        ]);

        try {
            $response = Http::timeout(30)->asForm()->post($url, $post_data);

            Log::info('SSLCommerz Response:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                    if (isset($responseData['GatewayPageURL']) && !empty($responseData['GatewayPageURL'])) {
                        return response()->json([
                            'status' => 'success',
                            'redirect_url' => $responseData['GatewayPageURL'],
                            'tran_id' => $tranId
                        ]);
                    }
                }

                // ✅ Return detailed error info for debugging
                return response()->json([
                    'status' => 'fail',
                    'message' => 'SSLCommerz init failed',
                    'error' => $responseData['failedreason'] ?? 'Unknown error',
                    'details' => $responseData
                ], 400);
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'HTTP connection failed',
                'error' => 'Status: ' . $response->status()
            ], 500);

        } catch (\Exception $e) {
            Log::error('Payment Exception:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'fail',
                'message' => 'Payment system error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function success(Request $request)
    {
        Log::info('Payment Success:', $request->all());
        return response()->json(['status' => 'Payment Success', 'data' => $request->all()]);
    }

    public function fail(Request $request)
    {
        Log::info('Payment Failed:', $request->all());
        return response()->json(['status' => 'Payment Failed', 'data' => $request->all()]);
    }

    public function cancel(Request $request)
    {
        Log::info('Payment Cancelled:', $request->all());
        return response()->json(['status' => 'Payment Cancelled', 'data' => $request->all()]);
    }

    public function ipn(Request $request)
    {
        Log::info('Payment IPN:', $request->all());
        return response('OK', 200);
    }
}
