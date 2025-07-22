<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('variants')->latest()->paginate(12);
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'role' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            'team' => 'required|string',
            'image' => 'required|image|max:2048',
            'variants' => 'required|array',
            'variants.*.color' => 'required|string',
            'variants.*.size' => 'required|string',
            'variants.*.stock' => 'required|integer'
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        foreach ($data['variants'] as $variant) {
            $product->variants()->create($variant);
        }

        return response()->json($product->load('variants'), 201);
    }

    public function show(Request $request, $id)
    {
        $product = Product::with('variants')->findOrFail($id);

        $query = $product->variants();

        if ($request->filled('color')) {
            $query->where('color', $request->color);
        }

        if ($request->filled('size')) {
            $query->where('size', $request->size);
        }

        $matchedVariants = $query->get();
        $stock = $matchedVariants->sum('stock');

        return response()->json([
            'product' => $product,
            'filtered_stock' => $stock,
            'matched_variants' => $matchedVariants
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::with('variants')->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string',
            'role' => 'sometimes|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'category_id' => 'sometimes|exists:categories,id',
            'team' => 'sometimes|string',
            'image' => 'nullable|image|max:2048',
            'variants' => 'nullable|array',
            'variants.*.color' => 'required_with:variants|string',
            'variants.*.size' => 'required_with:variants|string',
            'variants.*.stock' => 'required_with:variants|integer'
        ]);

        // Handle image replacement
        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        // 🔁 Replace all variants (for simplicity)
        if (isset($data['variants'])) {
            $product->variants()->delete(); // remove old
            foreach ($data['variants'] as $variant) {
                $product->variants()->create($variant); // add new
            }
        }

        return response()->json($product->load('variants'));
    }


    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->variants()->delete();
        $product->delete();

        return response()->json(['message' => 'Product and variants deleted']);
    }
}
