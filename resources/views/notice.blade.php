<div {{ $attributes->merge(['class' => 'sunset-notice', 'role' => 'status']) }}
     data-sunset="{{ $notice->sunset->date }}"
     style="padding: .75rem 1rem; border-left: 4px solid #d29922; background: rgba(210, 153, 34, .12); color: inherit; font: inherit;">
    <strong>{{ $notice->message }}</strong>

    @if ($notice->reason())
        {{ $notice->reason() }}
    @endif

    @if ($notice->replacement)
        <a href="{{ $notice->replacement }}">Use its replacement instead.</a>
    @endif
</div>
