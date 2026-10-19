@can('manage-msc')
    <a href="{{ route($mscRoutePrefix.'.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' => request()->routeIs($mscRoutePrefix.'.*'), 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs($mscRoutePrefix.'.*')]) @if(request()->routeIs($mscRoutePrefix.'.*')) aria-current="page" @endif>MSC</a>
@endcan
