<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Show all categories (paginated)
    public function index()
    {
        $categories = Category::paginate(10);
        return response()->json([
            'success' => true,
            'message' => 'Category list fetched successfully',
            'data' => $categories
        ]);
    }

    // Show one category
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Category fetched successfully',
            'data' => $category
        ]);
    }

    // Store new category
    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
        ], [
            'category_name.required' => 'Category name is required',
            'category_name.max' => 'Category name must not exceed 255 characters'
        ]);

        $category = Category::create([
            'category_name' => $request->category_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    // Update category
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'category_name' => 'required|string|max:255',
        ]);

        $category->update([
            'category_name' => $request->category_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    // Delete category
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
