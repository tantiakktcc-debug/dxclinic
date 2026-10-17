<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Print Document' }}</title>
    <!-- Styles for print -->
    <style>
        @page {
            margin: 0;
            size: {{ $pageSize ?? 'A5' }};
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #ffffff;
            color: #000000;
            line-height: 1.4;
        }
        /* Specific print adjustments will be added per document type */
    </style>
</head>
<body>
    {{ $slot }}
    <script>
        window.onload = function() {
            @if(isset($autoPrint) && $autoPrint)
                window.print();
            @endif
        }
    </script>
</body>
</html>
