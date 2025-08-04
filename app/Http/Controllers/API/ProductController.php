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
        // Build query with relationships
        $query = Product::with(['variants', 'collections']);

        // Apply category filter if provided
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Get per_page parameter (default 12, max 100)
        $perPage = min($request->get('per_page', 12), 100);

        // Order by latest and paginate
        $products = $query->latest()->paginate($perPage);

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
            'variants.*.stock' => 'required|integer',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'exists:collections,id',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        foreach ($data['variants'] as $variant) {
            $product->variants()->create($variant);
        }

        if ($request->has('collection_ids')) {
            $product->collections()->attach($request->collection_ids);
        }

        return response()->json($product->load(['variants', 'collections']), 201);
    }

    public function show(Request $request, $id)
    {
        $product = Product::with(['variants', 'collections'])->findOrFail($id);

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
        $product = Product::with(['variants', 'collections'])->findOrFail($id);

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
            'variants.*.stock' => 'required_with:variants|integer',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'exists:collections,id',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        if (isset($data['variants'])) {
            $product->variants()->delete();
            foreach ($data['variants'] as $variant) {
                $product->variants()->create($variant);
            }
        }

        if ($request->has('collection_ids')) {
            $product->collections()->sync($request->collection_ids);
        }

        return response()->json($product->load(['variants', 'collections']));
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->variants()->delete();
        $product->collections()->detach();
        $product->delete();

        return response()->json(['message' => 'Product and variants deleted']);
    }
}
