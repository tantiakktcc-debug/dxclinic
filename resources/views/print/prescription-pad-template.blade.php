<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $template->name }} - {{ $mode === 'proof' ? 'Proof PDF' : 'Production Print' }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @page {
            size: {{ $paperWidthMm }}mm {{ $paperHeightMm }}mm;
            margin: 0;
        }

        html, body {
            width: {{ $paperWidthMm }}mm;
            height: {{ $paperHeightMm }}mm;
            margin: 0;
            padding: 0;
            position: relative;
            background-color: transparent;
            overflow: hidden;
            font-family: 'Noto Sans Devanagari', 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            color: #000000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .pad-canvas {
            position: relative;
            width: {{ $paperWidthMm }}mm;
            height: {{ $paperHeightMm }}mm;
            overflow: hidden;
        }

        .background-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $paperWidthMm }}mm;
            height: {{ $paperHeightMm }}mm;
            z-index: 1;
            pointer-events: none;
        }

        .background-layer img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }

        .elements-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $paperWidthMm }}mm;
            height: {{ $paperHeightMm }}mm;
            z-index: 10;
        }

        .pad-element {
            position: absolute;
            box-sizing: border-box;
            overflow: hidden;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
        }

        .element-line {
            border-bottom-style: solid;
        }

        .element-rect {
            border-style: solid;
        }
    </style>
</head>
<body>
    <div class="pad-canvas">
        {{-- Proof Mode Watermark Banner & Background Scan --}}
        @if($mode === 'proof')
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 5mm; background: rgba(234, 88, 12, 0.9); color: #ffffff; font-family: 'Helvetica', sans-serif; font-size: 7.5pt; font-weight: bold; text-align: center; line-height: 5mm; z-index: 999; letter-spacing: 0.5px;">
                *** PROOF MODE — FOR ALIGNMENT &amp; TEST ONLY — NOT FOR PRODUCTION PRINT ***
            </div>
            @if(!empty($backgroundDataUri))
                <div class="background-layer">
                    <img src="{{ $backgroundDataUri }}" alt="Pad Scan Background">
                </div>
            @endif
        @endif

        {{-- Dynamic Elements Layer --}}
        <div class="elements-layer">
            @foreach($renderedElements as $elem)
                @if($elem['is_visible'])
                    @php
                        $fontSizePt = (float)$elem['font_size'];
                        $lineHeight = !empty($elem['line_height']) ? (float)$elem['line_height'] : 1.15;
                        $style = "left: {$elem['final_x_mm']}mm; " .
                                 "top: {$elem['final_y_mm']}mm; " .
                                 "width: {$elem['final_w_mm']}mm; " .
                                 "height: {$elem['final_h_mm']}mm; " .
                                 "font-family: '{$elem['font_family']}', 'Noto Sans Devanagari', 'DejaVu Sans', sans-serif; " .
                                 "font-size: {$fontSizePt}pt; " .
                                 "font-weight: {$elem['font_weight']}; " .
                                 "font-style: {$elem['font_style']}; " .
                                 "text-align: {$elem['text_align']}; " .
                                 "line-height: {$lineHeight}; " .
                                 "color: {$elem['color']}; ";
                        if (!empty($elem['rotation'])) {
                            $style .= "transform: rotate({$elem['rotation']}deg); transform-origin: center center; ";
                        }
                    @endphp

                    @if($elem['element_type'] === 'LINE')
                        <div class="pad-element element-line" style="{{ $style }} border-bottom-style: {{ $elem['line_style'] }}; border-bottom-width: {{ $elem['line_width_mm'] }}mm; border-bottom-color: {{ $elem['color'] }}; height: 0;"></div>
                    @elseif($elem['element_type'] === 'RECTANGLE')
                        <div class="pad-element element-rect" style="{{ $style }} border-style: {{ $elem['border_style'] }}; border-width: {{ $elem['border_width_mm'] }}mm; border-color: {{ $elem['color'] }}; background-color: {{ $elem['fill_color'] === 'transparent' ? 'transparent' : $elem['fill_color'] }}; border-radius: {{ $elem['border_radius_mm'] }}mm;"></div>
                    @elseif($elem['element_type'] === 'IMAGE')
                        @php
                            $imgOpacity = isset($elem['config_json']['opacity']) ? (float)$elem['config_json']['opacity'] : 1.0;
                            $imgStyle = $style . " opacity: {$imgOpacity};";
                        @endphp
                        @if(!empty($elem['image_data_uri']))
                            <div class="pad-element element-image" style="{{ $imgStyle }}">
                                <img src="{{ $elem['image_data_uri'] }}" style="width: 100%; height: 100%; display: block;" alt="Prescription Image Element">
                            </div>
                        @endif
                    @else
                        <div class="pad-element" style="{{ $style }}">{!! nl2br(e(trim((string)$elem['rendered_value']))) !!}</div>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</body>
</html>
