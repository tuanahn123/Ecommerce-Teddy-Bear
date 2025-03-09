<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Lấy danh sách tất cả danh mục
     */
    public function index()
    {
        return response()->json(Category::all());
    }

    /**
     * Tạo mới danh mục
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Xử lý upload hình ảnh (nếu có)
        $imagePath = $request->hasFile('image') ? $request->file('image')->store('category_images', 'public') : null;

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return response()->json(['message' => 'Danh mục đã được tạo.', 'category' => $category], 201);
    }

    /**
     * Xem chi tiết một danh mục
     */
    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['error' => 'Danh mục không tồn tại.'], 404);
        }
        return response()->json($category);
    }

    /**
     * Cập nhật danh mục
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['error' => 'Danh mục không tồn tại.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => 'nullable|string',
        ]);

        // Xử lý upload hình ảnh (nếu có)
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('category_images', 'public');
        }

        // Cập nhật dữ liệu
        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? $category->image,
        ]);

        return response()->json(['message' => 'Danh mục đã được cập nhật.', 'category' => $category]);
    }

    /**
     * Xóa danh mục
     */
    public function destroy($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['error' => 'Danh mục không tồn tại.'], 404);
        }

        $category->delete();
        return response()->json(['message' => 'Danh mục đã được xóa.']);
    }
}
