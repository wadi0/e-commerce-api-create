<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $orders = Order::where('user_id', $user->id)
                ->with(['orderItems.product'])
                ->latest()
                ->paginate(10);

            return response()->json($orders);
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch orders'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Log the incoming request
            Log::info('Order creation request:', $request->all());
            
            $user = $request->user();
            
            // Log user info
            Log::info('User attempting to create order:', ['user_id' => $user->id]);

            $data = $request->validate([
                'cart_ids' => 'required|array|min:1',
                'cart_ids.*' => 'integer|exists:carts,id',
                'shipping_address' => 'required|string|max:1000',
                'phone' => 'required|string|max:20',
                'payment_method' => 'required|string|in:sslcommerz,cod',
                'notes' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            // Get cart items with detailed logging
            $cartItems = Cart::whereIn('id', $data['cart_ids'])
                ->where('user_id', $user->id)
                ->with('product')
                ->get();

            Log::info('Found cart items:', ['count' => $cartItems->count(), 'cart_ids' => $data['cart_ids']]);

            if ($cartItems->isEmpty()) {
                Log::warning('No cart items found', ['user_id' => $user->id, 'cart_ids' => $data['cart_ids']]);
                return response()->json([
                    'error' => 'No valid cart items found',
                    'message' => 'The selected cart items do not exist or do not belong to you'
                ], 400);
            }

            // Verify all cart items have products
            $itemsWithoutProduct = $cartItems->filter(function ($item) {
                return !$item->product;
            });

            if ($itemsWithoutProduct->count() > 0) {
                Log::error('Cart items without products found', ['items' => $itemsWithoutProduct->pluck('id')]);
                return response()->json([
                    'error' => 'Some cart items have invalid products',
                    'message' => 'Please refresh your cart and try again'
                ], 400);
            }

            // Calculate totals with validation
            $subtotal = 0;
            foreach ($cartItems as $item) {
                if (!$item->product || !$item->product->price) {
                    throw new \Exception("Invalid product price for cart item {$item->id}");
                }
                $subtotal += $item->product->price * $item->quantity;
            }

            $shipping = $subtotal > 50 ? 0 : 10;
            $tax = $subtotal * 0.08;
            $total = $subtotal + $shipping + $tax;

            Log::info('Order calculations:', [
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'tax' => $tax,
                'total' => $total
            ]);

            // Create order with better transaction ID
            $transactionId = 'TXN_' . time() . '_' . $user->id . '_' . rand(1000, 9999);
            
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . '-' . $user->id,
                'transaction_id' => $transactionId,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'shipping_address' => $data['shipping_address'],
                'phone' => $data['phone'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            Log::info('Order created:', ['order_id' => $order->id, 'transaction_id' => $transactionId]);

            // Create order items
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                    'total' => $item->product->price * $item->quantity,
                ]);
            }

            Log::info('Order items created:', ['count' => $cartItems->count()]);

            // Remove items from cart
            Cart::whereIn('id', $data['cart_ids'])
                ->where('user_id', $user->id)
                ->delete();

            Log::info('Cart items removed');

            DB::commit();

            // Load order with relations
            $order->load(['orderItems.product']);

            Log::info('Order completed successfully:', ['order_id' => $order->id]);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error:', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors(),
                'message' => 'Please check your input and try again'
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to create order',
                'message' => 'An internal server error occurred. Please try again later.',
                'debug_message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $order = Order::where('user_id', $user->id)
                ->with(['orderItems.product'])
                ->findOrFail($id);

            return response()->json($order);
        } catch (\Exception $e) {
            Log::error('Error fetching order: ' . $e->getMessage());
            return response()->json(['error' => 'Order not found'], 404);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'status' => 'required|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
                'payment_status' => 'sometimes|string|in:pending,paid,failed,refunded'
            ]);

            $order = Order::findOrFail($id);
            $order->update($data);

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update order status'], 500);
        }
    }

    // Add these methods to your OrderController

    public function getAllOrders(Request $request)
    {
        try {
            $query = Order::with(['user', 'orderItems.product']);

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment status  
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            $perPage = $request->get('per_page', 10);
            $orders = $query->latest()->paginate($perPage);

            return response()->json($orders);
        } catch (\Exception $e) {
            Log::error('Error fetching all orders: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch orders'], 500);
        }
    }

    public function getOrderDetails($id)
    {
        try {
            $order = Order::with(['user', 'orderItems.product'])->findOrFail($id);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Order not found'], 404);
        }
    }
}
