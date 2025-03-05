<?php

namespace App\Http\Controllers;

use App\Models\AttributeType;
use App\Models\AttributeValue;
use App\Models\VariationAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttributeValueController extends Controller
{
    /**
     * Hiển thị danh sách attribute values
     */
    public function index(Request $request)
    {
        $query = AttributeValue::with('attributeType');

        // Lọc theo attribute_type_id nếu có
        if ($request->has('attribute_type_id')) {
            $query->where('attribute_type_id', $request->attribute_type_id);
        }

        $attributeValues = $query->get();

        return response()->json([
            'data' => $attributeValues
        ]);
    }

    /**
     * Hiển thị chi tiết attribute value
     */
    public function show($id)
    {
        $attributeValue = AttributeValue::with('attributeType')->find($id);

        if (!$attributeValue) {
            return response()->json([
                'message' => 'Không tìm thấy attribute Value'
            ], 404);
        }

        return response()->json([
            'data' => $attributeValue
        ]);
    }

    /**
     * Tạo mới attribute value
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'attribute_type_id' => 'required|exists:attribute_types,id',
                'value' => 'required|string|max:255',
                'display_value' => 'required|string|max:255',
            ]);

            // Kiểm tra xem attribute type có tồn tại không
            $attributeType = AttributeType::findOrFail($validated['attribute_type_id']);

            // Kiểm tra xem giá trị đã tồn tại cho attribute type này chưa
            $exists = AttributeValue::where('attribute_type_id', $validated['attribute_type_id'])
                ->where('value', $validated['value'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Giá trị này đã tồn tại cho loại thuộc tính này.'
                ], 422);
            }

            $attributeValue = AttributeValue::create($validated);

            return response()->json([
                'message' => 'Attribute value đã được tạo thành công',
                'data' => $attributeValue
            ], 201);
        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo attribute value: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật attribute value
     */
    public function update(Request $request, $id)
    {
        try {
            $attributeValue = AttributeValue::findOrFail($id);

            $validated = $request->validate([
                'attribute_type_id' => 'required|exists:attribute_types,id',
                'value' => 'required|string|max:255',
                'display_value' => 'required|string|max:255',
            ]);

            // Kiểm tra xem giá trị đã tồn tại cho attribute type này chưa (ngoại trừ ID hiện tại)
            $exists = AttributeValue::where('attribute_type_id', $validated['attribute_type_id'])
                ->where('value', $validated['value'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Giá trị này đã tồn tại cho loại thuộc tính này.'
                ], 422);
            }

            $attributeValue->update($validated);

            return response()->json([
                'message' => 'Attribute value đã được cập nhật thành công',
                'data' => $attributeValue
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi cập nhật attribute value: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa attribute value
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $attributeValue = AttributeValue::findOrFail($id);

            // Kiểm tra xem attribute value có đang được sử dụng không
            $inUse = VariationAttribute::where('attribute_value_id', $id)->exists();
            if ($inUse) {
                return response()->json([
                    'message' => 'Không thể xóa giá trị thuộc tính này vì đang được sử dụng trong các biến thể sản phẩm.'
                ], 400);
            }

            $attributeValue->delete();

            DB::commit();

            return response()->json([
                'message' => 'Attribute value đã được xóa thành công'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi xóa attribute value: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách attribute values theo attribute type
     */
    public function getByAttributeType($attributeTypeId)
    {
        try {
            $attributeType = AttributeType::findOrFail($attributeTypeId);

            $attributeValues = AttributeValue::where('attribute_type_id', $attributeTypeId)->get();

            return response()->json([
                'data' => $attributeValues
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi lấy attribute values theo type: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
