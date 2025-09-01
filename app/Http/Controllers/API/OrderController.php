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
            $user = $request->user();

            $data = $request->validate([
                'cart_ids' => 'required|array|min:1',
                'cart_ids.*' => 'integer|exists:carts,id',
                'shipping_address' => 'required|string',
                'phone' => 'required|string',
                'payment_method' => 'required|string|in:sslcommerz,cod',
                'notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            // Get selected cart items
            $cartItems = Cart::whereIn('id', $data['cart_ids'])
                ->where('user_id', $user->id)
                ->with('product')
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['error' => 'No valid cart items found'], 400);
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += $item->product->price * $item->quantity;
            }

            $shipping = $subtotal > 50 ? 0 : 10; // Free shipping over $50
            $tax = $subtotal * 0.08; // 8% tax
            $total = $subtotal + $shipping + $tax;

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . '-' . $user->id,
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

            // Remove items from cart
            Cart::whereIn('id', $data['cart_ids'])
                ->where('user_id', $user->id)
                ->delete();

            DB::commit();

            // Load order with items and products
            $order->load(['orderItems.product']);

            Log::info('Order created successfully', ['order_id' => $order->id]);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create order', 'message' => $e->getMessage()], 500);
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
}
