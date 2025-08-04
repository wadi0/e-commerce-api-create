<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::with('product')
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($cartItems);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::with('variants')->findOrFail($request->product_id);
        $totalStock = $product->variants->sum('stock');

        $cartItem = Cart::where('user_id', Auth::id())
            ->where('product_id', $request->product_id)
            ->first();

        $existingQty = $cartItem ? $cartItem->quantity : 0;
        $newQty = $existingQty + $request->quantity;

        if ($newQty > $totalStock) {
            return response()->json([
                'message' => 'Stock limited. Only ' . ($totalStock - $existingQty) . ' items left.',
                'available' => max($totalStock - $existingQty, 0),
            ], 422);
        }

        if ($cartItem) {
            $cartItem->quantity = $newQty;
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cartItem->load('product'),
        ]);
    }

    // Cart quantity update method - এই method add করুন
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem = Cart::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$cartItem) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        // Check stock availability
        $product = Product::with('variants')->findOrFail($cartItem->product_id);
        $totalStock = $product->variants->sum('stock');

        if ($request->quantity > $totalStock) {
            return response()->json([
                'message' => 'Stock limited. Only ' . $totalStock . ' items available.',
                'available' => $totalStock,
            ], 422);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json([
            'message' => 'Cart updated successfully',
            'cart' => $cartItem->load('product'),
        ]);
    }

    public function destroy($id)
    {
        $deleted = Cart::where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Product removed from cart']);
        }

        return response()->json(['message' => 'Cart item not found'], 404);
    }
}
