<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index()
    {
        return Collection::withCount('products')->get();
    }

    public function show($slug)
    {
        $collection = Collection::where('slug', $slug)->firstOrFail();
        $products = $collection->products()->with('variants')->latest()->paginate(12);

        return response()->json([
            'collection' => $collection->only(['id', 'name', 'slug']),
            'products' => $products
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:collections,name',
            'slug' => 'required|string|unique:collections,slug',
        ]);

        $collection = Collection::create($data);
        return response()->json($collection, 201);
    }

    public function update(Request $request, $id)
    {
        $collection = Collection::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|unique:collections,name,' . $id,
            'slug' => 'sometimes|string|unique:collections,slug,' . $id,
        ]);

        $collection->update($data);
        return response()->json($collection);
    }

    public function destroy($id)
    {
        $collection = Collection::findOrFail($id);
        $collection->delete();

        return response()->json(['message' => 'Collection deleted successfully']);
    }
}
