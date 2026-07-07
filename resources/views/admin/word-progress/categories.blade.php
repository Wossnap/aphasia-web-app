@extends('admin.layout')

@section('title', 'Category Progress')
@section('header', 'Category Progress')

@section('content')
    <a href="{{ route('admin.word-progress.index', request()->only('user_id', 'word_id')) }}"
       class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 hover:underline mb-4">
        <i class="fas fa-arrow-left"></i> Back to dashboard
    </a>

    <!-- Filters -->
    <form method="GET" class="mb-4 bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-4">
        <input type="hidden" name="user_id" value="{{ $userId }}">
        <input type="hidden" name="word_id" value="{{ $wordId }}">
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</label>
            <select name="status" class="block w-44 border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">All statuses</option>
                <option value="needs_focus" {{ $status === 'needs_focus' ? 'selected' : '' }}>Needs Focus</option>
                <option value="improving" {{ $status === 'improving' ? 'selected' : '' }}>Improving</option>
                <option value="doing_great" {{ $status === 'doing_great' ? 'selected' : '' }}>Doing Great</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
                <i class="fas fa-filter mr-1.5"></i> Filter
            </button>
            @if($status)
                <a href="{{ route('admin.word-progress.categories', request()->only('user_id', 'word_id')) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <!-- Desktop: table -->
    <div class="hidden md:block bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Words Tried</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $sort === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center gap-1 hover:text-gray-700">
                                Avg Score <i class="fas fa-sort-{{ $sort === 'asc' ? 'up' : 'down' }} text-[10px]"></i>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($list as $cat)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $cat['category']->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cat['word_count'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $cat['avg_score'] }}/10</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @include('admin.partials.status-badge', ['status' => $cat['status']])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 whitespace-nowrap text-sm text-gray-500 text-center font-medium">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="text-4xl text-gray-300"><i class="fas fa-chart-line"></i></div>
                                    <p>No categories match this filter.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile: cards -->
    <div class="md:hidden space-y-3">
        @forelse($list as $cat)
            <div class="bg-white shadow rounded-lg px-4 py-3">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="font-bold text-gray-900 text-lg truncate">{{ $cat['category']->name }}</div>
                    <div class="text-lg font-bold text-gray-900 flex-shrink-0">{{ $cat['avg_score'] }}/10</div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @include('admin.partials.status-badge', ['status' => $cat['status']])
                    <span class="text-xs text-gray-500">{{ $cat['word_count'] }} word{{ $cat['word_count'] === 1 ? '' : 's' }} tried</span>
                </div>
            </div>
        @empty
            <div class="bg-white shadow rounded-lg px-6 py-12 text-center text-sm text-gray-500">
                <div class="text-4xl text-gray-300 mb-2"><i class="fas fa-chart-line"></i></div>
                <p>No categories match this filter.</p>
            </div>
        @endforelse
    </div>
@endsection
