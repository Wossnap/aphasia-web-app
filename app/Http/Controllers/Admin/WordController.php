<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmharicWord;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WordController extends Controller
{

    public function index(Request $request)
    {
        $query = AmharicWord::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('word', 'like', "%{$search}%")
                  ->orWhere('meaning', 'like', "%{$search}%")
                  ->orWhereJsonContains('transliterations', $search);
            });
        }

        if ($request->has('category') && $request->category) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->category);
            });
        }

        $words = $query->with('categories')->paginate(15);
        $categories = Category::all();

        return view('admin.words.index', compact('words', 'categories'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('admin.words.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'meaning' => 'nullable|string|max:255',
            'transliterations' => 'nullable|string',
            'audio_file' => 'nullable|mimes:mp3,wav,ogg|max:10240',
            'image_file' => 'nullable|image|max:5120',
            'gif_file' => 'nullable|mimes:gif|max:10240',
            'show_in_random' => 'boolean',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'levels' => 'array',
            'levels.*' => 'integer|min:1|max:10'
        ]);

        // Process transliterations
        $transliterations = [];
        if ($validated['transliterations']) {
            $transliterations = array_map('trim', explode(',', $validated['transliterations']));
        }

        $word = AmharicWord::create([
            'word' => $validated['word'],
            'meaning' => $validated['meaning'],
            'transliterations' => $transliterations,
            'show_in_random' => $request->has('show_in_random'),
        ]);

        // Handle file uploads to public folder
        if ($request->hasFile('audio_file')) {
            $audioFile = $request->file('audio_file');
            $originalName = pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $audioFile->getClientOriginalExtension();
            $audioFileName = $originalName . '_' . $word->id . '.' . $extension;
            $audioFile->move(public_path('audio'), $audioFileName);
            $word->update(['audio_path' => $audioFileName]);
        }

        if ($request->hasFile('image_file')) {
            $imageFile = $request->file('image_file');
            $originalName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $imageFile->getClientOriginalExtension();
            $imageFileName = $originalName . '_' . $word->id . '.' . $extension;
            $imageFile->move(public_path('images'), $imageFileName);
            $word->update(['image_path' => $imageFileName]);
        }

        if ($request->hasFile('gif_file')) {
            $gifFile = $request->file('gif_file');
            $originalName = pathinfo($gifFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $gifFile->getClientOriginalExtension();
            $gifFileName = $originalName . '_' . $word->id . '.' . $extension;
            $gifFile->move(public_path('gifs'), $gifFileName);
            $word->update(['gif_path' => $gifFileName]);
        }

        // Attach categories with levels
        if ($request->has('categories')) {
            foreach ($request->categories as $index => $categoryId) {
                $level = $request->levels[$index] ?? 1;
                $word->categories()->attach($categoryId, ['level' => $level]);
            }
        }

        return redirect()->route('admin.words.index')->with('success', 'Word created successfully.');
    }

    public function show(AmharicWord $word)
    {
        $word->load('categories');
        return view('admin.words.show', compact('word'));
    }

    public function edit(AmharicWord $word)
    {
        $word->load('categories');
        $categories = Category::all();
        return view('admin.words.edit', compact('word', 'categories'));
    }

    public function update(Request $request, AmharicWord $word)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'meaning' => 'nullable|string|max:255',
            'transliterations' => 'nullable|string',
            'audio_file' => 'nullable|mimes:mp3,wav,ogg|max:10240',
            'image_file' => 'nullable|image|max:5120',
            'gif_file' => 'nullable|mimes:gif|max:10240',
            'show_in_random' => 'boolean',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'levels' => 'array',
            'levels.*' => 'integer|min:1|max:10'
        ]);

        // Process transliterations
        $transliterations = [];
        if ($validated['transliterations']) {
            $transliterations = array_map('trim', explode(',', $validated['transliterations']));
        }

        $word->update([
            'word' => $validated['word'],
            'meaning' => $validated['meaning'],
            'transliterations' => $transliterations,
            'show_in_random' => $request->has('show_in_random'),
        ]);

        // Handle file uploads and deletions in public folder
        if ($request->hasFile('audio_file')) {
            if ($word->audio_path && file_exists(public_path('audio/' . $word->audio_path))) {
                unlink(public_path('audio/' . $word->audio_path));
            }
            $audioFile = $request->file('audio_file');
            $originalName = pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $audioFile->getClientOriginalExtension();
            $audioFileName = $originalName . '_' . $word->id . '.' . $extension;
            $audioFile->move(public_path('audio'), $audioFileName);
            $word->update(['audio_path' => $audioFileName]);
        }

        if ($request->hasFile('image_file')) {
            if ($word->image_path && file_exists(public_path('images/' . $word->image_path))) {
                unlink(public_path('images/' . $word->image_path));
            }
            $imageFile = $request->file('image_file');
            $originalName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $imageFile->getClientOriginalExtension();
            $imageFileName = $originalName . '_' . $word->id . '.' . $extension;
            $imageFile->move(public_path('images'), $imageFileName);
            $word->update(['image_path' => $imageFileName]);
        }

        if ($request->hasFile('gif_file')) {
            if ($word->gif_path && file_exists(public_path('gifs/' . $word->gif_path))) {
                unlink(public_path('gifs/' . $word->gif_path));
            }
            $gifFile = $request->file('gif_file');
            $originalName = pathinfo($gifFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $gifFile->getClientOriginalExtension();
            $gifFileName = $originalName . '_' . $word->id . '.' . $extension;
            $gifFile->move(public_path('gifs'), $gifFileName);
            $word->update(['gif_path' => $gifFileName]);
        }

        // Sync categories with levels
        $categoryData = [];
        if ($request->has('categories')) {
            foreach ($request->categories as $index => $categoryId) {
                $level = $request->levels[$index] ?? 1;
                $categoryData[$categoryId] = ['level' => $level];
            }
        }
        $word->categories()->sync($categoryData);

        return redirect()->route('admin.words.index')->with('success', 'Word updated successfully.');
    }

    public function destroy(AmharicWord $word)
    {
        // Delete associated files from public folder
        if ($word->audio_path && file_exists(public_path('audio/' . $word->audio_path))) {
            unlink(public_path('audio/' . $word->audio_path));
        }
        if ($word->image_path && file_exists(public_path('images/' . $word->image_path))) {
            unlink(public_path('images/' . $word->image_path));
        }
        if ($word->gif_path && file_exists(public_path('gifs/' . $word->gif_path))) {
            unlink(public_path('gifs/' . $word->gif_path));
        }

        $word->delete();

        return redirect()->route('admin.words.index')->with('success', 'Word deleted successfully.');
    }
}
