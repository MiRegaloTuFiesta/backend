<?php

namespace App\Http\Controllers;

use App\Models\WishTemplate;
use Illuminate\Http\Request;

class WishTemplateController extends Controller
{
    /**
     * Admin: List templates with filtering
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = WishTemplate::with('categories');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->whereHas('categories', function($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Admin: Store a new template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id'
        ]);

        $template = WishTemplate::create($validated);

        if ($request->has('category_ids')) {
            $template->categories()->sync($validated['category_ids']);
        }

        return response()->json($template->load('categories'), 201);
    }

    /**
     * Admin: Update an existing template
     */
    public function update(Request $request, $id)
    {
        $template = WishTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id'
        ]);

        $template->update($validated);

        if ($request->has('category_ids')) {
            $template->categories()->sync($validated['category_ids']);
        }

        return response()->json($template->load('categories'));
    }

    /**
     * Admin: Delete a template
     */
    public function destroy($id)
    {
        $template = WishTemplate::findOrFail($id);
        $template->delete();
        return response()->json(null, 204);
    }

    /**
     * PublicIndex: List templates for creators
     */
    public function publicIndex(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = WishTemplate::with('categories');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->whereHas('categories', function($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        return response()->json($query->latest()->get());
    }
}
