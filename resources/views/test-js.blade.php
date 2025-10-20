<!DOCTYPE html>
<html>
<head>
    <title>JS Test</title>
</head>
<body>
    <h1>Testing Filament JS</h1>
    <p>Laravel version: {{ app()->version() }}</p>

    <!-- Загружаем файлы в правильном порядке -->
    <script src="{{ asset('js/filament/filament/app.js') }}"></script>
    <script src="{{ asset('js/filament/support/support.js') }}"></script>

    <script>
        console.log('Testing filamentDropdown availability');
        console.log('Alpine:', typeof window.Alpine);
        console.log('Alpine.data:', typeof window.Alpine?.data);

        // Ждем инициализации Alpine
        document.addEventListener('alpine:init', () => {
            console.log('Alpine initialized');
            try {
                const dropdown = window.Alpine.data('filamentDropdown');
                console.log('filamentDropdown found:', typeof dropdown);
                document.body.innerHTML += '<p style="color: green;">✅ filamentDropdown is available!</p>';
            } catch (e) {
                console.error('filamentDropdown error:', e);
                document.body.innerHTML += '<p style="color: red;">❌ filamentDropdown error: ' + e.message + '</p>';
            }
        });

        // Также проверим через таймер как резерв
        setTimeout(() => {
            if (typeof window.Alpine !== 'undefined') {
                document.body.innerHTML += '<p style="color: blue;">ℹ️ Alpine is loaded</p>';
            } else {
                document.body.innerHTML += '<p style="color: red;">❌ Alpine not loaded</p>';
            }
        }, 2000);
    </script>
</body>
</html>