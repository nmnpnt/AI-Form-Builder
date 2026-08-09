@if (session('status'))
    @php $flashId = 'flash-'.uniqid(); @endphp
    <div id="{{ $flashId }}"
         class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2 transition-opacity duration-500 mb-4">
        {{ session('status') }}
    </div>
    <script>
        (function () {
            const el = document.getElementById('{{ $flashId }}');
            if (! el) return;
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 3000);
        })();
    </script>
@endif