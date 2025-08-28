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
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:50',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
        ]);

        // ✅ Round amount to 2 decimal places to fix floating point issues
        $amount = round($request->amount, 2);

        // ✅ Generate unique transaction ID
        $tranId = 'TXN_' . time() . '_' . rand(1000, 9999);

        $post_data = [
            'store_id' => env('SSLCZ_STORE_ID'),
            'store_passwd' => env('SSLCZ_STORE_PASS'),
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'success_url' => url('/api/payment/success'),
            'fail_url' => url('/api/payment/fail'),
            'cancel_url' => url('/api/payment/cancel'),
            'ipn_url' => url('/api/payment/ipn'),

            // ✅ Customer Information - All Required
            'cus_name' => substr($request->name, 0, 50), // Limit length
            'cus_email' => $request->email,
            'cus_add1' => substr($request->address, 0, 200),
            'cus_add2' => '',
            'cus_city' => 'Dhaka',
            'cus_state' => 'Dhaka',
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $request->phone,
            'cus_fax' => '',

            // ✅ Shipment Information - Required
            'ship_name' => substr($request->name, 0, 50),
            'ship_add1' => substr($request->address, 0, 200),
            'ship_add2' => '',
            'ship_city' => 'Dhaka',
            'ship_state' => 'Dhaka',
            'ship_postcode' => '1000',
            'ship_country' => 'Bangladesh',

            // ✅ Product Information - Required
            'product_name' => 'E-commerce Order',
            'product_category' => 'General',
            'product_profile' => 'general',

            // ✅ Optional but recommended
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'multi_card_name' => '',
            'value_a' => '',
            'value_b' => '',
            'value_c' => '',
            'value_d' => '',
        ];

        // ✅ Use correct API URL
        $url = env('SSLCZ_SANDBOX')
            ? "https://sandbox.sslcommerz.com/gwprocess/v3/api.php"
            : "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

        Log::info('SSLCommerz Payment Request:', [
            'url' => $url,
            'store_id' => env('SSLCZ_STORE_ID'),
            'amount' => $amount,
            'tran_id' => $tranId,
            'sandbox' => env('SSLCZ_SANDBOX'),
        ]);

        try {
            $response = Http::timeout(30)->asForm()->post($url, $post_data);

            Log::info('SSLCommerz Response:', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // ✅ Check response status
                if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                    if (isset($responseData['GatewayPageURL']) && !empty($responseData['GatewayPageURL'])) {
                        return response()->json([
                            'status' => 'success',
                            'redirect_url' => $responseData['GatewayPageURL'],
                            'tran_id' => $tranId
                        ]);
                    } else {
                        Log::error('No GatewayPageURL in response', $responseData);
                        return response()->json([
                            'status' => 'fail',
                            'message' => 'Payment gateway URL not received',
                            'error' => 'No redirect URL'
                        ], 400);
                    }
                } else {
                    // ✅ SSLCommerz returned error
                    $errorMsg = $responseData['failedreason'] ?? 'Unknown SSLCommerz error';
                    Log::error('SSLCommerz Error:', $responseData);

                    return response()->json([
                        'status' => 'fail',
                        'message' => 'SSLCommerz init failed',
                        'error' => $errorMsg,
                        'details' => $responseData
                    ], 400);
                }
            } else {
                Log::error('HTTP Error from SSLCommerz:', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'status' => 'fail',
                    'message' => 'Payment gateway connection failed',
                    'error' => 'HTTP ' . $response->status()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Payment Exception:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        Log::info('Payment Success Callback:', $request->all());

        // ✅ Validate the payment
        $validation = $this->validatePayment($request);

        if ($validation['valid']) {
            // Update your database here
            // Mark order as paid, update stock, etc.

            return response()->json([
                'status' => 'Payment Successful',
                'data' => $request->all()
            ]);
        } else {
            return response()->json([
                'status' => 'Payment Validation Failed',
                'message' => $validation['message']
            ], 400);
        }
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

        // Validate and process IPN
        $validation = $this->validatePayment($request);

        if ($validation['valid']) {
            // Process the payment
            // Update database, send emails, etc.
        }

        return response('OK', 200);
    }

    private function validatePayment($request)
    {
        $storeId = env('SSLCZ_STORE_ID');
        $storePassword = env('SSLCZ_STORE_PASS');

        $tranId = $request->tran_id;
        $amount = $request->amount;
        $currency = $request->currency;

        if (empty($tranId)) {
            return ['valid' => false, 'message' => 'Transaction ID missing'];
        }

        // ✅ Validate with SSLCommerz
        $url = env('SSLCZ_SANDBOX')
            ? "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php"
            : "https://securepay.sslcommerz.com/validator/api/validationserverAPI.php";

        try {
            $response = Http::asForm()->post($url, [
                'store_id' => $storeId,
                'store_passwd' => $storePassword,
                'tran_id' => $tranId,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'VALID') {
                    return ['valid' => true, 'data' => $data];
                }
            }

            return ['valid' => false, 'message' => 'Payment validation failed'];

        } catch (\Exception $e) {
            Log::error('Payment validation error:', ['error' => $e->getMessage()]);
            return ['valid' => false, 'message' => 'Validation service error'];
        }
    }
}
