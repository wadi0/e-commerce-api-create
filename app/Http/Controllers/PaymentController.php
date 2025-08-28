<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function initPayment(Request $request)
    {
        // Validate required fields
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'address' => 'required|string',
        ]);

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
            // Add required fields that might be missing
            'cus_city' => $request->city ?? 'Dhaka',
            'cus_state' => $request->state ?? 'Dhaka',
            'cus_postcode' => $request->postcode ?? '1000',
            'cus_country' => 'Bangladesh',
            'cus_fax' => '',
            'ship_name' => $request->name,
            'ship_add1' => $request->address,
            'ship_city' => $request->city ?? 'Dhaka',
            'ship_state' => $request->state ?? 'Dhaka',
            'ship_postcode' => $request->postcode ?? '1000',
            'ship_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => 'Order Payment',
            'product_category' => 'General',
            'product_profile' => 'general',
        ];

        $url = env('SSLCZ_SANDBOX')
            ? "https://sandbox.sslcommerz.com/gwprocess/v3/api.php"
            : "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

        // Log the request for debugging
        Log::info('SSLCommerz Request:', [
            'url' => $url,
            'data' => $post_data
        ]);

        try {
            $response = Http::asForm()->post($url, $post_data);

            // Log the response for debugging
            Log::info('SSLCommerz Response:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            $responseData = $response->json();

            // Check if response is successful and has the gateway URL
            if ($response->successful() &&
                isset($responseData['status']) &&
                $responseData['status'] === 'SUCCESS' &&
                isset($responseData['GatewayPageURL'])) {

                return response()->json([
                    'status' => 'success',
                    'redirect_url' => $responseData['GatewayPageURL']
                ]);
            } else {
                // Log the error for debugging
                Log::error('SSLCommerz Error:', [
                    'response' => $responseData,
                    'status' => $response->status()
                ]);

                return response()->json([
                    'status' => 'fail',
                    'message' => 'SSLCommerz init failed',
                    'error' => $responseData['failedreason'] ?? 'Unknown error'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('SSLCommerz Exception:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'fail',
                'message' => 'Payment gateway connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function success(Request $request)
    {
        // Log successful payment
        Log::info('Payment Success:', $request->all());

        // Here you can update order status
        return response()->json(['status' => 'Payment Success', 'data' => $request->all()]);
    }

    public function fail(Request $request)
    {
        Log::info('Payment Failed:', $request->all());
        return response()->json(['status' => 'Payment Failed']);
    }

    public function cancel(Request $request)
    {
        Log::info('Payment Cancelled:', $request->all());
        return response()->json(['status' => 'Payment Cancelled']);
    }
}
