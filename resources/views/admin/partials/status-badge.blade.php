{{--
    Renders a color-coded status pill. Handles both status vocabularies used
    across the word-progress pages: word-level (mastered/improving/needs_practice)
    and category/level rollups (doing_great/improving/needs_focus).

    Expected variable: $status
--}}
@if($status === 'mastered' || $status === 'doing_great')
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
        {{ $status === 'mastered' ? 'Mastered' : 'Doing Great' }}
    </span>
@elseif($status === 'improving')
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
        Improving
    </span>
@else
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200">
        {{ $status === 'needs_practice' ? 'Needs Practice' : 'Needs Focus' }}
    </span>
@endif
