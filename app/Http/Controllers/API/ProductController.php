<?php
// File: app/Http/Controllers/API/ProductController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class ProductController extends Controller
{
    private function getCloudinaryInstance()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true
            ]
        ]);

        return new Cloudinary();
    }

    public function index(Request $request)
    {
        try {
            $query = Product::with(['variants', 'collections']);

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $perPage = min($request->get('per_page', 12), 100);
            $products = $query->latest()->paginate($perPage);

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in products index: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch products', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
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

            // Ensure stock is integer
            if (isset($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as &$variant) {
                    $variant['stock'] = (int) $variant['stock'];
                }
            }

            // ✅ Working Cloudinary upload using base SDK
            if ($request->hasFile('image')) {
                $file = $request->file('image');

                if (!$file->isValid()) {
                    throw new \Exception('Invalid file uploaded');
                }

                Log::info('Starting Cloudinary upload', [
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize()
                ]);

                // Get Cloudinary instance
                $cloudinary = $this->getCloudinaryInstance();

                // Upload using the uploadApi
                $uploadResult = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'products',
                    'resource_type' => 'image',
                    'transformation' => [
                        'quality' => 'auto',
                        'fetch_format' => 'auto'
                    ]
                ]);

                $data['image'] = $uploadResult['secure_url'];
                $data['cloudinary_public_id'] = $uploadResult['public_id'];

                Log::info('Cloudinary upload successful', [
                    'url' => $data['image'],
                    'public_id' => $data['cloudinary_public_id']
                ]);
            }

            $product = Product::create($data);

            $variants = $data['variants'] ?? [];
            foreach ($variants as $variant) {
                $product->variants()->create($variant);
            }

            if (!empty($request->collection_ids)) {
                $product->collections()->attach($request->collection_ids);
            }

            return response()->json($product->load(['variants', 'collections']), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error: ', $e->errors());
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create product', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Error fetching product: ' . $e->getMessage());
            return response()->json(['error' => 'Product not found', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
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

            // Handle new image upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $cloudinary = $this->getCloudinaryInstance();

                // Delete old image if exists
                if ($product->cloudinary_public_id) {
                    try {
                        $cloudinary->uploadApi()->destroy($product->cloudinary_public_id);
                        Log::info('Old image deleted from Cloudinary', [
                            'public_id' => $product->cloudinary_public_id
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old image: ' . $e->getMessage());
                    }
                }

                // Upload new image
                $uploadResult = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'products',
                    'resource_type' => 'image',
                    'transformation' => [
                        'quality' => 'auto',
                        'fetch_format' => 'auto'
                    ]
                ]);

                $data['image'] = $uploadResult['secure_url'];
                $data['cloudinary_public_id'] = $uploadResult['public_id'];

                Log::info('New image uploaded to Cloudinary', [
                    'url' => $data['image'],
                    'public_id' => $data['cloudinary_public_id']
                ]);
            }

            $product->update($data);

            if (isset($data['variants'])) {
                $product->variants()->delete();
                foreach ($data['variants'] as $variant) {
                    $variant['stock'] = (int) $variant['stock'];
                    $product->variants()->create($variant);
                }
            }

            if (isset($request->collection_ids)) {
                $product->collections()->sync($request->collection_ids ?? []);
            }

            return response()->json($product->load(['variants', 'collections']));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update product', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            if ($product->cloudinary_public_id) {
                try {
                    $cloudinary = $this->getCloudinaryInstance();
                    $cloudinary->uploadApi()->destroy($product->cloudinary_public_id);
                    Log::info('Image deleted from Cloudinary', [
                        'public_id' => $product->cloudinary_public_id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to delete image from Cloudinary: ' . $e->getMessage());
                }
            }

            $product->variants()->delete();
            $product->collections()->detach();
            $product->delete();

            return response()->json(['message' => 'Product and variants deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete product', 'message' => $e->getMessage()], 500);
        }
    }
}