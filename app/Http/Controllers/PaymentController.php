<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function initPayment(Request $request)
    {
        // ✅ Log everything for debugging
        Log::info('=== PAYMENT REQUEST START ===');
        Log::info('Request Method:', [$request->method()]);
        Log::info('Request Headers:', $request->headers->all());
        Log::info('Request Body:', $request->all());

        // ✅ Get amount and ensure it's a number
        $amount = $request->input('amount');
        Log::info('Original amount:', ['amount' => $amount, 'type' => gettype($amount)]);

        // Convert to float and ensure minimum
        $amount = floatval($amount);
        if ($amount <= 0) {
            Log::error('Invalid amount:', ['amount' => $amount]);
            return response()->json([
                'status' => 'fail',
                'message' => 'Invalid amount',
                'amount_received' => $amount
            ], 400);
        }

        // Round to 2 decimal places
        $amount = round($amount, 2);
        Log::info('Final amount:', ['amount' => $amount]);

        // ✅ Generate transaction ID
        $tranId = 'TXN_' . time() . '_' . rand(100000, 999999);

        // ✅ Build payload
        $post_data = [
            'store_id' => env('SSLCZ_STORE_ID'),
            'store_passwd' => env('SSLCZ_STORE_PASS'),
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'success_url' => 'https://zaw-collection-laravel-api-admin-fr.vercel.app/payment/success',
            'fail_url' => 'https://zaw-collection-laravel-api-admin-fr.vercel.app/payment/fail',
            'cancel_url' => 'https://zaw-collection-laravel-api-admin-fr.vercel.app/payment/cancel',
            'ipn_url' => url('/api/payment/ipn'),

            // Customer Info
            'cus_name' => $request->input('name', 'Customer'),
            'cus_email' => $request->input('email', 'customer@example.com'),
            'cus_add1' => $request->input('address', 'Dhaka'),
            'cus_add2' => '',
            'cus_city' => 'Dhaka',
            'cus_state' => 'Dhaka',
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $request->input('phone', '01700000000'),
            'cus_fax' => '',

            // Shipping Info
            'ship_name' => $request->input('name', 'Customer'),
            'ship_add1' => $request->input('address', 'Dhaka'),
            'ship_add2' => '',
            'ship_city' => 'Dhaka',
            'ship_state' => 'Dhaka',
            'ship_postcode' => '1000',
            'ship_country' => 'Bangladesh',

            // Product Info
            'product_name' => 'E-commerce Order',
            'product_category' => 'General',
            'product_profile' => 'general',

            // Additional
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'multi_card_name' => '',
            'value_a' => '',
            'value_b' => '',
            'value_c' => '',
            'value_d' => ''
        ];

        Log::info('SSLCommerz Payload:', $post_data);

        // ✅ API URL
        $url = env('SSLCZ_SANDBOX')
            ? "https://sandbox.sslcommerz.com/gwprocess/v3/api.php"
            : "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

        Log::info('API URL:', ['url' => $url]);

        try {
            // ✅ Make API call
            $response = Http::timeout(30)
                ->asForm()
                ->post($url, $post_data);

            Log::info('SSLCommerz HTTP Response:', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('SSLCommerz JSON Response:', $responseData);

                // Check for success
                if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                    if (!empty($responseData['GatewayPageURL'])) {
                        Log::info('Payment Success - Redirecting:', [
                            'url' => $responseData['GatewayPageURL']
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'redirect_url' => $responseData['GatewayPageURL'],
                            'tran_id' => $tranId
                        ]);
                    }
                }

                // Failed response
                Log::error('SSLCommerz Failed Response:', $responseData);
                return response()->json([
                    'status' => 'fail',
                    'message' => 'SSLCommerz init failed',
                    'error' => $responseData['failedreason'] ?? 'Unknown error',
                    'details' => $responseData
                ], 400);

            } else {
                Log::error('HTTP Error:', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'status' => 'fail',
                    'message' => 'Connection failed',
                    'error' => 'HTTP ' . $response->status()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'fail',
                'message' => 'System error',
                'error' => $e->getMessage()
            ], 500);
        } finally {
            Log::info('=== PAYMENT REQUEST END ===');
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
