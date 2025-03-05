<?php

namespace App\Http\Controllers;

use App\Models\AttributeType;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttributeTypeController extends Controller
{
    /**
     * Hiển thị danh sách attribute types
     */
    public function index()
    {
        $attributeTypes = AttributeType::with('attributeValues')->get();

        return response()->json([
            'data' => $attributeTypes
        ]);
    }

    /**
     * Hiển thị chi tiết một attribute type
     */
    public function show($id)
    {
        $attributeType = AttributeType::with('attributeValues')->find($id);

        if (!$attributeType) {
            return response()->json([
                'message' => 'Không tìm thấy atrribute type'
            ], 404);
        }

        return response()->json([
            'data' => $attributeType
        ]);
    }

    /**
     * Tạo mới một attribute type
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:attribute_types,name',
                'display_name' => 'required|string|max:255',
            ]);

            $attributeType = AttributeType::create($validated);

            return response()->json([
                'message' => 'Attribute type đã được tạo thành công',
                'data' => $attributeType
            ], 201);
        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo attribute type: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật attribute type
     */
    public function update(Request $request, $id)
    {
        try {
            $attributeType = AttributeType::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:attribute_types,name,' . $id,
                'display_name' => 'required|string|max:255',
            ]);

            $attributeType->update($validated);

            return response()->json([
                'message' => 'Attribute type đã được cập nhật thành công',
                'data' => $attributeType
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi cập nhật attribute type: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa attribute type
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $attributeType = AttributeType::findOrFail($id);

            // Kiểm tra xem attribute type có đang được sử dụng không
            $countValues = AttributeValue::where('attribute_type_id', $id)->count();
            if ($countValues > 0) {
                return response()->json([
                    'message' => 'Không thể xóa attribute type này vì đang có ' . $countValues . ' giá trị được liên kết.'
                ], 400);
            }

            $attributeType->delete();

            DB::commit();

            return response()->json([
                'message' => 'Attribute type đã được xóa thành công'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi xóa attribute type: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
