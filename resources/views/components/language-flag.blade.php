@props(['country'])

<svg {{ $attributes->class(['inline-block h-4 w-6 align-middle shrink-0']) }} viewBox="0 0 60 40" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    @if($country === 'br')
        <path fill="#009739" d="M0 0h60v40H0z" />
        <path fill="#ffdf00" d="m30 5 25 15-25 15L5 20z" />
        <circle cx="30" cy="20" r="10" fill="#012169" />
        <path d="M21 16q10-1 18 8" fill="none" stroke="#fff" stroke-width="2" />
    @elseif($country === 'us')
        <path fill="#fff" d="M0 0h60v40H0z" />
        <path stroke="#b22234" stroke-width="3.08" d="M0 1.54h60M0 7.69h60M0 13.85h60M0 20h60M0 26.15h60M0 32.31h60M0 38.46h60" />
        <path fill="#3c3b6e" d="M0 0h26v21.54H0z" />
        <path fill="#fff" d="M3 3h2v2H3zm6 0h2v2H9zm6 0h2v2h-2zm6 0h2v2h-2zM6 7h2v2H6zm6 0h2v2h-2zm6 0h2v2h-2zM3 11h2v2H3zm6 0h2v2H9zm6 0h2v2h-2zm6 0h2v2h-2zM6 15h2v2H6zm6 0h2v2h-2zm6 0h2v2h-2zM3 19h2v2H3zm6 0h2v2H9zm6 0h2v2h-2zm6 0h2v2h-2z" />
    @endif
</svg>
