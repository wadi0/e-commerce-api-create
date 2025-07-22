<?php

//namespace App\Http\Controllers\API;
//
//use App\Http\Controllers\Controller;
//use Illuminate\Http\Request;
//use App\Models\Product;
//use Illuminate\Support\Facades\Storage;
//
//class ProductController extends Controller
//{
//    // Product List with filter + sort
//    public function index(Request $request)
//    {
//        $query = Product::query();
//
//        // 🔍 Search by name
//        if ($request->filled('search')) {
//            $query->where('name', 'like', '%' . $request->search . '%');
//        }
//
//        // 🔍 Filter by category
//        if ($request->filled('category_id')) {
//            $query->where('category_id', $request->category_id);
//        }
//
//        // 🔍 Filter by team
//        if ($request->filled('team')) {
//            $query->where('team', $request->team);
//        }
//
//        // 🔍 Filter by color
//        if ($request->filled('color')) {
//            $query->where('color', $request->color);
//        }
//
//        // 🔍 Filter by size
//        if ($request->filled('size')) {
//            $query->where('size', $request->size);
//        }
//
//        // 🔍 Filter by variant
//        if ($request->filled('variant')) {
//            $query->where('variant', $request->variant);
//        }
//
//        // 🔃 Sorting
//        switch ($request->sort) {
//            case 'price_asc':
//                $query->orderBy('price', 'asc');
//                break;
//            case 'price_desc':
//                $query->orderBy('price', 'desc');
//                break;
//            case 'latest':
//            default:
//                $query->orderBy('created_at', 'desc');
//        }
//
//        // ✅ Pagination support (optional)
//        $products = $query->paginate(12); // 12 items per page
//
//        return response()->json($products);
//    }
//
//    public function store(Request $request)
//    {
//        $data = $request->validate([
//            'name' => 'required|string',
//            'description' => 'nullable|string',
//            'price' => 'required|numeric',
//            'category_id' => 'required|exists:categories,id',
//            'stock' => 'required|integer',
//            'size' => 'required|string',
//            'team' => 'required|string',
//            'color' => 'required|string',
//            'variant' => 'required|in:home,away,special,other',
//            'image' => 'required|image|max:2048'
//        ]);
//
//        if ($request->hasFile('image')) {
//            $data['image'] = $request->file('image')->store('products', 'public');
//        }
//
//        $product = Product::create($data);
//
//        return response()->json($product, 201);
//    }
//
//    public function show($id)
//    {
//        return Product::findOrFail($id);
//    }
//
//    public function update(Request $request, $id)
//    {
//        $product = Product::findOrFail($id);
//
//        $data = $request->validate([
//            'name' => 'sometimes|string',
//            'description' => 'nullable|string',
//            'price' => 'sometimes|numeric',
//            'category_id' => 'sometimes|exists:categories,id',
//            'stock' => 'sometimes|integer',
//            'size' => 'sometimes|string',
//            'team' => 'sometimes|string',
//            'color' => 'sometimes|string',
//            'variant' => 'sometimes|in:home,away,special,other',
//            'image' => 'nullable|image|max:2048'
//        ]);
//
//        if ($request->hasFile('image')) {
//            if ($product->image) {
//                Storage::disk('public')->delete($product->image);
//            }
//            $data['image'] = $request->file('image')->store('products', 'public');
//        }
//
//        $product->update($data);
//
//        return response()->json($product);
//    }
//
//    public function destroy($id)
//    {
//        $product = Product::findOrFail($id);
//        if ($product->image) {
//            Storage::disk('public')->delete($product->image);
//        }
//        $product->delete();
//
//        return response()->json(['message' => 'Product deleted']);
//    }
//}

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
