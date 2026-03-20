<div {{ $attributes->merge(['class' => 'font-bold text-xl tracking-tighter']) }}>
    @php
        $name = config('app.name');
        $firstLetter = substr($name, 0, 1);
        $rest = substr($name, 1);
    @endphp
    <span class="text-blue-600">{{ $firstLetter }}</span>{{ $rest }}
</div>