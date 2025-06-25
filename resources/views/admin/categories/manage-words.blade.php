@extends('admin.layout')

@section('title', 'Manage Words - ' . $category->name)
@section('header', 'Manage Words: ' . $category->name)

@section('content')
    <div class="space-y-6">
        <!-- Add New Word to Category -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Add Word to Category</h3>
                
                <form method="POST" action="{{ route('admin.categories.add-word', $category) }}" class="flex space-x-4">
                    @csrf
                    <div class="flex-1">
                        <select name="word_id" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a word</option>
                            @foreach($availableWords as $word)
                                <option value="{{ $word->id }}">{{ $word->word }} - {{ $word->meaning ?? 'No meaning' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <select name="level" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Level {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Add Word
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Words in Category -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Words in {{ $category->name }}</h3>
                
                @if($categoryWords->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Word</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meaning</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Media</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($categoryWords as $word)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $word->word }}</div>
                                            <div class="text-sm text-gray-500">
                                                @if($word->transliterations)
                                                    {{ implode(', ', $word->transliterations) }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $word->meaning ?? 'No meaning' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="POST" action="{{ route('admin.categories.update-word-level', [$category, $word]) }}" class="flex items-center space-x-2">
                                                @csrf
                                                @method('PATCH')
                                                <select name="level" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                                    @for($i = 1; $i <= 10; $i++)
                                                        <option value="{{ $i }}" {{ $word->pivot->level == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                                                    @endfor
                                                </select>
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs">
                                                    Update
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex space-x-1">
                                                @if($word->audio_path)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Audio</span>
                                                @endif
                                                @if($word->image_path)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Image</span>
                                                @endif
                                                @if($word->gif_path)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">GIF</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form method="POST" action="{{ route('admin.categories.remove-word', [$category, $word]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" 
                                                        onclick="return confirm('Are you sure you want to remove this word from the category?')">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">No words in this category yet.</p>
                @endif
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex justify-between">
            <a href="{{ route('admin.categories.index') }}" 
               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                Back to Categories
            </a>
            <a href="{{ route('admin.categories.show', $category) }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                View Category Details
            </a>
        </div>
    </div>
@endsection
