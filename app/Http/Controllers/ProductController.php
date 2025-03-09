<?php

namespace App\Http\Controllers;

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
        Log::info("Has file images?", ['has_file' => $request->hasFile('images')]);

        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], Response::HTTP_NOT_FOUND);
        }

        try {
            $validated = $request->validate([
                'category_id' => 'exists:categories,id',
                'name' => 'string|max:255',
                'base_price' => 'numeric|min:0',
                'description' => 'nullable|string',
                'featured' => 'boolean',
                'status' => 'boolean',

                //TODO Validate hình ảnh
                'images' => 'nullable',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',

                //TODO Validate biến thể
                'variations' => 'nullable|array',
                'variations.*.id' => 'nullable|exists:product_variations,id',
                'variations.*.sku' => 'sometimes|string',
                'variations.*.price' => 'nullable|numeric|min:0',
                'variations.*.discount_price' => 'nullable|numeric|min:0',
                'variations.*.stock_quantity' => 'nullable|integer|min:0',
                'variations.*.is_default' => 'nullable|boolean',
                'variations.*.status' => 'nullable|boolean',
                'variations.*.attributes' => 'nullable|array',
                'variations.*.attributes.*.attribute_value_id' => 'sometimes|exists:attribute_values,id',
                'variations.*.images' => 'nullable',
                'variations.*.images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("Validation failed:", $e->errors());
            return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
        }

        DB::beginTransaction();
        try {
            //TODO Cập nhật thông tin sản phẩm
            $product->update([
                'category_id' => $validated['category_id'] ?? $product->category_id,
                'name' => $validated['name'] ?? $product->name,
                'slug' => $validated['name'] ? Str::slug($validated['name']) : $product->slug,
                'description' => $validated['description'] ?? $product->description,
                'base_price' => $validated['base_price'] ?? $product->base_price,
                'featured' => filter_var($validated['featured'] ?? $product->featured, FILTER_VALIDATE_BOOLEAN),
                'status' => filter_var($validated['status'] ?? $product->status, FILTER_VALIDATE_BOOLEAN),
            ]);

            //TODO Cập nhật ảnh sản phẩm nếu có
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                //TODO Xử lý trường hợp images là một file duy nhất hoặc mảng files
                if (!is_array($images)) {
                    $images = [$images];
                }

                foreach ($images as $index => $image) {
                    $path = $image->store('product_images', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0, //TODO Ảnh đầu tiên là ảnh chính
                    ]);
                }
            }

            //TODO Cập nhật biến thể sản phẩm nếu có
            if (!empty($request->variations)) {
                foreach ($request->variations as $variationData) {
                    if (!empty($variationData['id'])) {
                        //TODO Tìm và cập nhật biến thể cũ
                        $variation = ProductVariation::find($variationData['id']);
                        if ($variation) {
                            $variation->update([
                                'sku' => $variationData['sku'] ?? $variation->sku,
                                'price' => $variationData['price'] ?? $variation->price,
                                'discount_price' => $variationData['discount_price'] ?? $variation->discount_price,
                                'stock_quantity' => $variationData['stock_quantity'] ?? $variation->stock_quantity,
                                'is_default' => filter_var($variationData['is_default'] ?? $variation->is_default, FILTER_VALIDATE_BOOLEAN),
                                'status' => filter_var($variationData['status'] ?? $variation->status, FILTER_VALIDATE_BOOLEAN),
                            ]);
                        }
                    } else {
                        //TODO Tạo biến thể mới
                        $variation = ProductVariation::create([
                            'product_id' => $product->id,
                            'sku' => $variationData['sku'] ?? 'SKU-' . $product->id . '-' . time(),
                            'price' => $variationData['price'] ?? null,
                            'discount_price' => $variationData['discount_price'] ?? null,
                            'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                            'is_default' => filter_var($variationData['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'status' => filter_var($variationData['status'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        ]);
                    }

                    //TODO Cập nhật thuộc tính biến thể nếu có
                    if (!empty($variationData['attributes'])) {
                        VariationAttribute::where('product_variation_id', $variation->id)->delete();
                        foreach ($variationData['attributes'] as $attribute) {
                            VariationAttribute::create([
                                'product_variation_id' => $variation->id,
                                'attribute_value_id' => $attribute['attribute_value_id'],
                            ]);
                        }
                    }

                    //TODO Cập nhật ảnh biến thể nếu có
                    if (isset($variationData['images'])) {
                        $varImages = $variationData['images'];
                        //TODO Xử lý trường hợp images là một file duy nhất hoặc mảng files
                        if (!is_array($varImages)) {
                            $varImages = [$varImages];
                        }

                        foreach ($varImages as $imgIndex => $image) {
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
            }

            DB::commit();
            return response()->json(['message' => 'Sản phẩm đã được cập nhật thành công.', 'product' => $product], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi cập nhật sản phẩm:", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
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
}
