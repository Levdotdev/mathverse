@php $activePage = $activePage ?? 'dashboard'; @endphp

<a href="/admin/dashboard" id="btn-overview"
   class="nav-link w-full {{ $activePage === 'dashboard' ? 'active' : '' }}">
    <i class="fas fa-microchip mr-3 w-5 text-red-500"></i> Mainframe
</a>
<a href="/admin/dashboard?section=stats" id="btn-stats"
   class="nav-link w-full {{ $activePage === 'analytics' ? 'active' : '' }}">
    <i class="fas fa-chart-bar mr-3 w-5 text-pink-400"></i> Analytics
</a>
<a href="/admin/quizzes"
   class="nav-link w-full {{ $activePage === 'quizzes' ? 'active' : '' }}">
    <i class="fas fa-vr-cardboard mr-3 w-5 text-purple-400"></i> VR Quiz Bees
</a>
<a href="/admin/quiz-library"
   class="nav-link w-full {{ $activePage === 'library' ? 'active' : '' }}">
    <i class="fas fa-book-open mr-3 w-5 text-blue-400"></i> Quiz Library
    @if(($adminPendingReportCount ?? 0) > 0)
        <span class="ml-auto min-w-5 h-5 px-1 rounded-full bg-red-500 text-black text-[9px] font-black flex items-center justify-center">{{ $adminPendingReportCount }}</span>
    @endif
</a>
<a href="/admin/dashboard?section=students" id="btn-students"
   class="nav-link w-full {{ $activePage === 'students' ? 'active' : '' }}">
    <i class="fas fa-user-graduate mr-3 w-5 text-cyan-400"></i> Students
</a>
<a href="/admin/dashboard?section=teachers" id="btn-teachers"
   class="nav-link w-full {{ $activePage === 'teachers' ? 'active' : '' }}">
    <i class="fas fa-chalkboard-teacher mr-3 w-5 text-blue-400"></i> Teachers
</a>
<a href="/admin/dashboard?section=role-verify" id="btn-role-verify"
   class="nav-link w-full {{ $activePage === 'verification' ? 'active' : '' }}">
    <i class="fas fa-user-shield mr-3 w-5 text-orange-400"></i> Verification
    @if(($adminPendingTeacherCount ?? 0) > 0)
        <span class="ml-auto min-w-5 h-5 px-1 rounded-full bg-orange-400 text-black text-[9px] font-black flex items-center justify-center">{{ $adminPendingTeacherCount }}</span>
    @endif
</a>
<a href="/admin/dashboard?section=notifications" id="btn-notifications"
   class="nav-link w-full {{ $activePage === 'notifications' ? 'active' : '' }}">
    <i class="fas fa-bell mr-3 w-5 text-yellow-400"></i> Notifications
    @if(($adminNotificationCount ?? 0) > 0)
        <span class="ml-auto min-w-5 h-5 px-1 rounded-full bg-yellow-400 text-black text-[9px] font-black flex items-center justify-center">{{ $adminNotificationCount }}</span>
    @endif
</a>
<a href="/admin/dashboard?section=audit" id="btn-audit"
   class="nav-link w-full {{ $activePage === 'audit' ? 'active' : '' }}">
    <i class="fas fa-clipboard-list mr-3 w-5 text-red-400"></i> Audit Log
</a>
<a href="/admin/dashboard?section=reports" id="btn-reports"
   class="nav-link w-full {{ $activePage === 'reports' ? 'active' : '' }}">
    <i class="fas fa-file-download mr-3 w-5 text-green-400"></i> Reports
</a>
