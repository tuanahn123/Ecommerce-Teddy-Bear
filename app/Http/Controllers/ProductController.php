<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\VariationAttribute;
use App\Models\VariationImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    //TODO Lấy danh sách sản phẩm
    public function index()
    {
        $products = Product::with(['categories', 'images', 'variations.attributes.attributeValue.attributeType', 'variations.attributes', 'variations.images'])->get();
        return response()->json($products, Response::HTTP_OK);
    }
    //TODO Tìm kiếm sản phẩm
    public function search(Request $request)
    {
        $query = Product::query()->with(['categories', 'images', 'variations.attributes.attributeValue.attributeType', 'variations.attributes', 'variations.images']);

        if ($request->has('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('category_name')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->category_name . '%');
            });
        }

        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        $products = $query->get();

        return response()->json($products);
    }
    //TODO Thêm sản phẩm mới
    public function store(Request $request)
    {
        Log::info("Dữ liệu request:", $request->all());

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'base_price' => 'required|numeric|min:0',
                'description' => 'nullable|string',
                'featured' => 'nullable|boolean',
                'status' => 'nullable|boolean',
                //TODO Validate hình ảnh
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',

                //TODO Validate biến thể - cập nhật theo cấu trúc frontend
                'variations' => 'required|array', // Biến thể là bắt buộc
                'variations.*.sku' => 'required|string|distinct', // SKU là bắt buộc và phải khác nhau
                'variations.*.price' => 'required|numeric|min:0', // Giá là bắt buộc
                'variations.*.discount_price' => 'nullable|numeric|min:0|lt:variations.*.price', // Giá giảm phải nhỏ hơn giá gốc
                'variations.*.stock_quantity' => 'required|integer|min:0', // Số lượng là bắt buộc
                'variations.*.is_default' => 'nullable|boolean',
                'variations.*.status' => 'nullable|boolean',
                'variations.*.attributes' => 'required|array', // Thuộc tính là bắt buộc
                'variations.*.attributes.*.attribute_type_id' => 'sometimes|exists:attribute_types,id',
                'variations.*.attributes.*.attribute_value_id' => 'required|exists:attribute_values,id',
                'variations.*.images' => 'nullable|array',
                'variations.*.images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            //TODO Ghi log lỗi
            Log::error("Validation failed:", $e->errors());

            //TODO Trả về lỗi dạng JSON
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        }

        //TODO Bắt đầu transaction
        DB::beginTransaction();
        try {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            //TODO Kiểm tra nếu slug đã tồn tại, thêm số vào cuối slug
            while (Product::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            //TODO Tạo sản phẩm
            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'category_id' => $validated['category_id'],
                'base_price' => $validated['base_price'],
                'description' => $validated['description'] ?? null,
                'featured' => filter_var($validated['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'status' => filter_var($validated['status'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            //TODO Thêm ảnh sản phẩm
            if (isset($validated['images']) && count($validated['images']) > 0) {
                foreach ($validated['images'] as $index => $image) {
                    $path = $image->store('product_images', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0, //TODO Ảnh đầu tiên là ảnh chính
                    ]);
                }
            }

            //TODO Thêm biến thể sản phẩm
            if (!empty($validated['variations'])) {
                $hasDefaultVariation = false;

                foreach ($validated['variations'] as $index => $variationData) {
                    // Kiểm tra SKU trùng lặp
                    if (ProductVariation::where('sku', $variationData['sku'])->exists()) {
                        throw new \Exception("SKU '{$variationData['sku']}' đã tồn tại trong hệ thống.");
                    }

                    $isDefault = filter_var($variationData['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    // Nếu đây là biến thể mặc định đầu tiên hoặc chưa có biến thể mặc định
                    if ($isDefault) {
                        $hasDefaultVariation = true;
                    }

                    $variation = ProductVariation::create([
                        'product_id' => $product->id,
                        'sku' => $variationData['sku'],
                        'price' => $variationData['price'],
                        'discount_price' => $variationData['discount_price'] ?? null,
                        'stock_quantity' => $variationData['stock_quantity'],
                        'is_default' => $isDefault,
                        'status' => filter_var($variationData['status'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ]);

                    //TODO Thêm thuộc tính biến thể
                    if (!empty($variationData['attributes'])) {
                        foreach ($variationData['attributes'] as $attribute) {
                            VariationAttribute::create([
                                'product_variation_id' => $variation->id,
                                'attribute_type_id' => $attribute['attribute_type_id'],
                                'attribute_value_id' => $attribute['attribute_value_id'],
                            ]);
                        }
                    }

                    //TODO Thêm ảnh biến thể
                    if (isset($variationData['images']) && count($variationData['images']) > 0) {
                        foreach ($variationData['images'] as $imgIndex => $image) {
                            if ($image instanceof \Illuminate\Http\UploadedFile) {
                                $path = $image->store('variation_images', 'public');
                                VariationImage::create([
                                    'product_variation_id' => $variation->id,
                                    'image_path' => $path,
                                    'is_primary' => $imgIndex === 0, //TODO Ảnh đầu tiên là ảnh chính
                                ]);
                            }
                        }
                    }
                }

                // Nếu không có biến thể mặc định, đặt biến thể đầu tiên làm mặc định
                if (!$hasDefaultVariation && count($validated['variations']) > 0) {
                    $firstVariation = ProductVariation::where('product_id', $product->id)->first();
                    if ($firstVariation) {
                        $firstVariation->update(['is_default' => true]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Sản phẩm đã được tạo thành công.',
                'product_id' => $product->id
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi tạo sản phẩm:", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    //TODO Xem chi tiết sản phẩm
    public function show($id)
    {
        $product = Product::with(['categories', 'images', 'variations.attributes.attributeValue.attributeType', 'variations.attributes', 'variations.images'])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($product, Response::HTTP_OK);
    }

    //TODO Cập nhật sản phẩm
    public function update(Request $request, $id)
    {
        Log::info("Dữ liệu request cập nhật:", $request->all());

        // Find the product first
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], Response::HTTP_NOT_FOUND);
        }

        // Validate the request - using less strict validation to allow for partial updates
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'category_id' => 'nullable|exists:categories,id',
                'base_price' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'featured' => 'nullable|boolean',
                'status' => 'nullable|boolean',

                // Image validation
                'existing_images' => 'nullable|array',
                'existing_images.*' => 'string',
                'delete_all_images' => 'nullable|boolean',

                // Variation validation
                'variations' => 'nullable|array',
                'variations.*.id' => 'nullable|integer',
                'variations.*.sku' => 'nullable|string',
                'variations.*.price' => 'nullable|numeric|min:0',
                'variations.*.discount_price' => 'nullable|numeric|min:0',
                'variations.*.stock_quantity' => 'nullable|integer|min:0',
                'variations.*.is_default' => 'nullable|boolean',
                'variations.*.status' => 'nullable|boolean',
                'variations.*.attributes' => 'nullable|array',
                'variations.*.existing_images' => 'nullable|array',
                'variations.*.existing_images.*' => 'string',
                'variations.*.delete_all_images' => 'nullable|boolean',

                // Variations to delete
                'delete_variations' => 'nullable|array',
                'delete_variations.*' => 'integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("Validation failed:", $e->errors());
            return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Update basic product information
            if (
                isset($validated['name']) || isset($validated['category_id']) ||
                isset($validated['base_price']) || isset($validated['description']) ||
                isset($validated['featured']) || isset($validated['status'])
            ) {

                $updateData = [];

                if (isset($validated['name'])) {
                    $updateData['name'] = $validated['name'];

                    // Update slug if name changes
                    $slug = Str::slug($validated['name']);
                    $originalSlug = $slug;
                    $counter = 1;

                    while (Product::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                        $slug = $originalSlug . '-' . $counter;
                        $counter++;
                    }

                    $updateData['slug'] = $slug;
                }

                if (isset($validated['category_id'])) $updateData['category_id'] = $validated['category_id'];
                if (isset($validated['base_price'])) $updateData['base_price'] = $validated['base_price'];
                if (isset($validated['description'])) $updateData['description'] = $validated['description'];
                if (isset($validated['featured'])) $updateData['featured'] = $validated['featured'];
                if (isset($validated['status'])) $updateData['status'] = $validated['status'];

                $product->update($updateData);
            }

            // 2. Handle product images
            if (isset($validated['delete_all_images']) && $validated['delete_all_images']) {
                // Delete all existing product images
                ProductImage::where('product_id', $product->id)->delete();
            }

            // Handle uploaded images (using direct file access instead of validation)
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                $primaryImageExists = ProductImage::where('product_id', $product->id)
                    ->where('is_primary', true)
                    ->exists();

                foreach ($images as $index => $image) {
                    $path = $image->store('product_images', 'public');

                    // If no primary image exists, set the first new image as primary
                    $isPrimary = (!$primaryImageExists && $index === 0);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $isPrimary
                    ]);

                    if ($isPrimary) {
                        $primaryImageExists = true;
                    }
                }
            }

            // Handle existing images (if submitted)
            if (isset($validated['existing_images']) && empty($validated['delete_all_images'])) {
                // First, remove any images not in the list
                $existingPaths = $validated['existing_images'];

                ProductImage::where('product_id', $product->id)
                    ->whereNotIn('image_path', $existingPaths)
                    ->delete();

                // Make sure at least one image is set as primary
                $primaryExists = ProductImage::where('product_id', $product->id)
                    ->where('is_primary', true)
                    ->exists();

                if (!$primaryExists && count($existingPaths) > 0) {
                    $firstImage = ProductImage::where('product_id', $product->id)
                        ->first();

                    if ($firstImage) {
                        $firstImage->update(['is_primary' => true]);
                    }
                }
            }

            // 3. Handle variations to delete
            if (isset($validated['delete_variations']) && !empty($validated['delete_variations'])) {
                ProductVariation::where('product_id', $product->id)
                    ->whereIn('id', $validated['delete_variations'])
                    ->delete();
            }

            // 4. Handle variations (update existing or create new)
            if (isset($validated['variations']) && !empty($validated['variations'])) {
                $defaultFound = false;

                foreach ($validated['variations'] as $variationData) {
                    // Check if it's an existing variation or a new one
                    if (!empty($variationData['id'])) {
                        // Update existing variation
                        $variation = ProductVariation::where('id', $variationData['id'])
                            ->where('product_id', $product->id)
                            ->first();

                        if ($variation) {
                            $updateData = [];

                            if (isset($variationData['sku'])) $updateData['sku'] = $variationData['sku'];
                            if (isset($variationData['price'])) $updateData['price'] = $variationData['price'];
                            if (isset($variationData['discount_price'])) $updateData['discount_price'] = $variationData['discount_price'];
                            if (isset($variationData['stock_quantity'])) $updateData['stock_quantity'] = $variationData['stock_quantity'];
                            if (isset($variationData['status'])) $updateData['status'] = $variationData['status'];

                            // Handle default status
                            if (isset($variationData['is_default'])) {
                                $updateData['is_default'] = $variationData['is_default'];

                                if ($variationData['is_default']) {
                                    $defaultFound = true;

                                    // Remove default from other variations
                                    ProductVariation::where('product_id', $product->id)
                                        ->where('id', '!=', $variation->id)
                                        ->update(['is_default' => false]);
                                }
                            }

                            $variation->update($updateData);
                        } else {
                            throw new \Exception("Không tìm thấy biến thể ID: " . $variationData['id'] . " cho sản phẩm này");
                        }
                    } else if (isset($variationData['sku'])) {
                        // Create new variation
                        // Check if SKU exists
                        if (ProductVariation::where('sku', $variationData['sku'])->exists()) {
                            throw new \Exception("SKU '{$variationData['sku']}' đã tồn tại trong hệ thống.");
                        }

                        $isDefault = isset($variationData['is_default']) ? $variationData['is_default'] : false;

                        if ($isDefault) {
                            $defaultFound = true;

                            // Set all other variations to non-default
                            ProductVariation::where('product_id', $product->id)
                                ->update(['is_default' => false]);
                        }

                        $variation = ProductVariation::create([
                            'product_id' => $product->id,
                            'sku' => $variationData['sku'],
                            'price' => $variationData['price'] ?? $product->base_price,
                            'discount_price' => $variationData['discount_price'] ?? null,
                            'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                            'is_default' => $isDefault,
                            'status' => $variationData['status'] ?? true
                        ]);
                    } else {
                        // Skip invalid variation data
                        continue;
                    }

                    // Handle variation attributes (if present)
                    if (isset($variationData['attributes']) && !empty($variationData['attributes'])) {
                        // Remove existing attributes
                        VariationAttribute::where('product_variation_id', $variation->id)->delete();

                        // Add new attributes
                        foreach ($variationData['attributes'] as $attribute) {
                            if (isset($attribute['attribute_value_id'])) {
                                VariationAttribute::create([
                                    'product_variation_id' => $variation->id,
                                    'attribute_type_id' => $attribute['attribute_type_id'] ?? null,
                                    'attribute_value_id' => $attribute['attribute_value_id']
                                ]);
                            }
                        }
                    }

                    // Handle variation images
                    if (isset($variationData['delete_all_images']) && $variationData['delete_all_images']) {
                        // Delete all existing variation images
                        VariationImage::where('product_variation_id', $variation->id)->delete();
                    }

                    // Check for uploaded variation images (using request directly)
                    $variationIndex = array_search($variationData, $validated['variations']);
                    $imageKey = 'variations.' . $variationIndex . '.images';

                    if ($request->hasFile($imageKey)) {
                        $images = $request->file($imageKey);
                        $primaryExists = VariationImage::where('product_variation_id', $variation->id)
                            ->where('is_primary', true)
                            ->exists();

                        foreach ($images as $index => $image) {
                            $path = $image->store('variation_images', 'public');
                            $isPrimary = (!$primaryExists && $index === 0);

                            VariationImage::create([
                                'product_variation_id' => $variation->id,
                                'image_path' => $path,
                                'is_primary' => $isPrimary
                            ]);

                            if ($isPrimary) {
                                $primaryExists = true;
                            }
                        }
                    }

                    // Handle existing variation images
                    if (isset($variationData['existing_images']) && !isset($variationData['delete_all_images'])) {
                        // Remove images not in the list
                        $existingPaths = $variationData['existing_images'];

                        VariationImage::where('product_variation_id', $variation->id)
                            ->whereNotIn('image_path', $existingPaths)
                            ->delete();

                        // Ensure one image is primary
                        $primaryExists = VariationImage::where('product_variation_id', $variation->id)
                            ->where('is_primary', true)
                            ->exists();

                        if (!$primaryExists && count($existingPaths) > 0) {
                            $firstImage = VariationImage::where('product_variation_id', $variation->id)
                                ->first();

                            if ($firstImage) {
                                $firstImage->update(['is_primary' => true]);
                            }
                        }
                    }
                }

                // Ensure at least one variation is marked as default
                if (!$defaultFound) {
                    $firstVariation = ProductVariation::where('product_id', $product->id)->first();

                    if ($firstVariation) {
                        $firstVariation->update(['is_default' => true]);
                    }
                }
            }

            DB::commit();

            // Return the updated product with relationships
            $updatedProduct = Product::with([
                'categories',
                'images',
                'variations.attributes.attributeValue.attributeType',
                'variations.attributes',
                'variations.images'
            ])->find($id);

            return response()->json([
                'message' => 'Sản phẩm đã được cập nhật thành công.',
                'product' => $updatedProduct
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi cập nhật sản phẩm:", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Đã xảy ra lỗi khi cập nhật sản phẩm.'
            ], 500);
        }
    }


    //TODO Xóa sản phẩm
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], Response::HTTP_NOT_FOUND);
        }

        $product->delete();

        return response()->json(['message' => 'Xóa sản phẩm thành công'], Response::HTTP_OK);
    }
    public function getProductsByCategoryId($categoryId)
    {
        // Tìm danh mục theo ID
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json(['error' => 'Danh mục không tồn tại.'], 404);
        }

        // Lấy tất cả sản phẩm thuộc danh mục này với tất cả các trường
        $products = Product::with([
            'images',
            'variations.attributes.attributeValue.attributeType',
            'variations.attributes',
            'variations.images',
            'categories' // Include the categories relationship if needed
        ])->where('category_id', $category->id)->get();

        return response()->json($products);
    }

    public function getProductsByCategorySlug($slug)
    {
        // Tìm danh mục theo slug
        $category = Category::where('slug', $slug)->first();

        if (!$category) {
            return response()->json(['error' => 'Danh mục không tồn tại.'], 404);
        }

        // Lấy tất cả sản phẩm thuộc danh mục này
        $products = Product::where('category_id', $category->id)->with(['images', 'variations.attributes.attributeValue.attributeType'])->get();

        return response()->json($products);
    }
}
