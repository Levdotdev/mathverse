@php
    $activePage = $activePage ?? request()->query('section', 'stats');
@endphp

<a href="/student/dashboard?section=stats" id="btn-stats"
   class="nav-link w-full {{ $activePage === 'stats' ? 'active' : '' }}">
    <i class="fas fa-chart-line mr-3 w-5 text-cyan-400"></i> My Stats
</a>
<a href="/student/dashboard?section=ranking" id="btn-ranking"
   class="nav-link w-full {{ $activePage === 'ranking' ? 'active' : '' }}">
    <i class="fas fa-trophy mr-3 w-5 text-yellow-500"></i> Ranking
</a>
<a href="/student/dashboard?section=class" id="btn-class"
   class="nav-link w-full {{ $activePage === 'class' ? 'active' : '' }}">
    <i class="fas fa-chalkboard mr-3 w-5 text-green-400"></i> My Classes
</a>
