<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class PaymentController extends Controller
{
    public function initPayment(Request $request)
    {
        Log::info('Payment init request:', $request->all());

        try {
            $data = $request->validate([
                'amount' => 'required|numeric|min:1',
                'order_id' => 'required|integer|exists:orders,id',
                'name' => 'required|string',
                'email' => 'required|email',
                'phone' => 'required|string',
                'address' => 'required|string'
            ]);

            $amount = round($data['amount'], 2);
            $order = Order::findOrFail($data['order_id']);
            $tranId = $order->transaction_id;

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

                'cus_name' => $data['name'],
                'cus_email' => $data['email'],
                'cus_add1' => $data['address'],
                'cus_city' => 'Dhaka',
                'cus_country' => 'Bangladesh',
                'cus_phone' => $data['phone'],

                'ship_name' => $data['name'],
                'ship_add1' => $data['address'],
                'ship_city' => 'Dhaka',
                'ship_country' => 'Bangladesh',

                'product_name' => 'Order #' . $order->order_number,
                'product_category' => 'General',
                'product_profile' => 'general',
            ];

            $url = env('SSLCZ_SANDBOX') 
                ? "https://sandbox.sslcommerz.com/gwprocess/v3/api.php"
                : "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

            $response = Http::asForm()->post($url, $post_data);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if ($responseData['status'] === 'SUCCESS' && !empty($responseData['GatewayPageURL'])) {
                    return response()->json([
                        'status' => 'success',
                        'redirect_url' => $responseData['GatewayPageURL'],
                        'tran_id' => $tranId
                    ]);
                }
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'Payment initialization failed'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payment init error: ' . $e->getMessage());
            return response()->json([
                'status' => 'fail',
                'message' => 'Payment system error'
            ], 500);
        }
    }

    public function success(Request $request)
    {
        Log::info('=== PAYMENT SUCCESS CALLBACK ===');
        Log::info('All request data:', ['data' => $request->all()]);
        Log::info('Request method:', ['method' => $request->method()]);
        
        $tran_id = $request->input('tran_id');
        $val_id = $request->input('val_id');
        
        Log::info('Transaction ID:', ['tran_id' => $tran_id]);
        Log::info('Validation ID:', ['val_id' => $val_id]);
        
        if (!$tran_id || !$val_id) {
            Log::error('Missing tran_id or val_id');
            return redirect('https://laravel-first-app-user-frontend.vercel.app/cart?status=error&message=Invalid payment response');
        }
        
        $order = Order::where('transaction_id', $tran_id)->first();
        
        if (!$order) {
            Log::error('Order not found for transaction:', ['tran_id' => $tran_id]);
            return redirect('https://laravel-first-app-user-frontend.vercel.app/cart?status=error&message=Order not found');
        }

        Log::info('Order found:', ['order_id' => $order->id, 'current_status' => $order->payment_status]);

        // Check if payment status is VALID from SSLCommerz response
        $sslStatus = $request->input('status');
        
        if ($sslStatus === 'VALID') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed'
            ]);
            
            Log::info('Order updated successfully:', ['order_id' => $order->id, 'new_status' => 'paid']);
            
            return redirect("https://laravel-first-app-user-frontend.vercel.app/order-success/{$order->id}?status=success");
        }
        
        Log::error('Payment status not valid', ['ssl_status' => $sslStatus, 'tran_id' => $tran_id]);
        return redirect('https://laravel-first-app-user-frontend.vercel.app/cart?status=failed&message=Payment validation failed');
    }

    public function fail(Request $request)
    {
        Log::info('Payment failed:', $request->all());
        
        $tran_id = $request->input('tran_id');
        if ($tran_id) {
            $order = Order::where('transaction_id', $tran_id)->first();
            if ($order) {
                $order->update(['payment_status' => 'failed']);
            }
        }

        return redirect('https://laravel-first-app-user-frontend.vercel.app/cart?status=failed');
    }

    public function cancel(Request $request)
    {
        Log::info('Payment cancelled:', $request->all());
        
        $tran_id = $request->input('tran_id');
        if ($tran_id) {
            $order = Order::where('transaction_id', $tran_id)->first();
            if ($order) {
                $order->update(['payment_status' => 'cancelled']);
            }
        }

        return redirect('https://laravel-first-app-user-frontend.vercel.app/cart?status=cancelled');
    }

    public function ipn(Request $request)
    {
        Log::info('IPN received:', $request->all());
        
        $tran_id = $request->input('tran_id');
        $status = $request->input('status');
        $val_id = $request->input('val_id');

        $order = Order::where('transaction_id', $tran_id)->first();

        if ($order && $status === 'VALID' && $this->validatePayment($tran_id, $val_id)) {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed'
            ]);
            
            Log::info('Order updated via IPN:', ['order_id' => $order->id]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function validatePayment($tran_id, $val_id)
    {
        try {
            $url = env('SSLCZ_SANDBOX') 
                ? "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php"
                : "https://securepay.sslcommerz.com/validator/api/validationserverAPI.php";

            $response = Http::asForm()->post($url, [
                'val_id' => $val_id,
                'store_id' => env('SSLCZ_STORE_ID'),
                'store_passwd' => env('SSLCZ_STORE_PASS'),
                'format' => 'json'
            ]);

            Log::info('Validation response:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['status']) && $data['status'] === 'VALID';
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Payment validation error: ' . $e->getMessage());
            return false;
        }
    }

    // Test method - manually mark payment as paid
    public function testPayment($transactionId)
    {
        $order = Order::where('transaction_id', $transactionId)->first();
        if ($order) {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed'
            ]);
            return response()->json(['message' => 'Payment marked as paid', 'order' => $order]);
        }
        return response()->json(['error' => 'Order not found'], 404);
    }
}