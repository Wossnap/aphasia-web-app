<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\AmharicWord;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function index()
    {
        $categories = Category::withCount('words')->paginate(15);
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
        ]);

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function show(Category $category)
    {
        $category->load('words');
        $words = $category->words()->paginate(15);
        return view('admin.categories.show', compact('category', 'words'));
    }

    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }

    public function manageWords(Category $category)
    {
        $categoryWords = $category->words()->get();
        $availableWords = AmharicWord::whereNotExists(function ($query) use ($category) {
            $query->select('*')
                  ->from('category_word')
                  ->where('category_id', $category->id)
                  ->whereColumn('amharic_word_id', 'amharic_words.id');
        })->get();

        return view('admin.categories.manage-words', compact('category', 'categoryWords', 'availableWords'));
    }

    public function addWord(Request $request, Category $category)
    {
        $validated = $request->validate([
            'word_id' => 'required|exists:amharic_words,id',
            'level' => 'required|integer|min:1|max:10',
        ]);

        $category->words()->attach($validated['word_id'], ['level' => $validated['level']]);

        return redirect()->back()->with('success', 'Word added to category successfully.');
    }

    public function removeWord(Category $category, AmharicWord $word)
    {
        $category->words()->detach($word->id);

        return redirect()->back()->with('success', 'Word removed from category successfully.');
    }

    public function updateWordLevel(Request $request, Category $category, AmharicWord $word)
    {
        $validated = $request->validate([
            'level' => 'required|integer|min:1|max:10',
        ]);

        $category->words()->updateExistingPivot($word->id, ['level' => $validated['level']]);

        return redirect()->back()->with('success', 'Word level updated successfully.');
    }
}
