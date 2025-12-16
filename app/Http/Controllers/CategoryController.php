<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::where('user_id', Auth::id())
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,liability',
        ]);

        // Check if category name already exists for this user
        $exists = Category::where('user_id', Auth::id())
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withInput()
                ->with('error', 'A category with this name already exists.');
        }

        Category::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        // Verify ownership
        if ($category->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this category.');
        }

        return view('categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        // Verify ownership
        if ($category->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this category.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,liability',
        ]);

        // Check if category name already exists for this user (excluding current category)
        $exists = Category::where('user_id', Auth::id())
            ->where('name', $validated['name'])
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return back()->withInput()
                ->with('error', 'A category with this name already exists.');
        }

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Verify ownership
        if ($category->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this category.');
        }

        // Check if category is being used
        if ($category->transactions()->exists()) {
            return back()->with('error', 'Cannot delete category that has transactions.');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
