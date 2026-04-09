<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * عرض كل الفئات
     */
    public function index()
    {
        return Category::all();
    }

    /**
     * إنشاء فئة جديدة (Admin فقط)
     */
    public function store(Request $request)
    {
       

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'message'  => 'Category created successfully',
            'category' => $category
        ]);
    }

    /**
     * عرض فئة واحدة
     */
    public function show($id)
    {
        return Category::findOrFail($id);
    }

    /**
     * تعديل فئة (Admin فقط)
     */
    public function update(Request $request, $id)
    {
        

        $category = Category::findOrFail($id);

        $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($request->only(['name', 'description']));

        return response()->json([
            'message'  => 'Category updated successfully',
            'category' => $category
        ]);
    }

    /**
     * حذف فئة (Admin فقط)
     */
    public function destroy(Request $request, $id)
    {
        

        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
