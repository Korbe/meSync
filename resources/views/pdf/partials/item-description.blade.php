{{-- Item description: first line as title, then detail text and "- " bullet lists --}}
@php
    $description = str_replace("\r", '', $description);
    [$firstLine, $details] = array_pad(explode("\n", $description, 2), 2, null);

    // Description starting directly with a bullet: no title line
    if (preg_match('/^\s*[-*•]\s+/u', $firstLine)) {
        [$firstLine, $details] = [null, $description];
    }
@endphp
@if ($firstLine !== null)
    <div class="item-title">{{ $firstLine }}</div>
@endif
@if ($details)
    {{-- Group the detail lines into plain text blocks and bullet lists --}}
    @php
        $blocks = [];
        foreach (explode("\n", $details) as $line) {
            $isBullet = (bool) preg_match('/^\s*[-*•]\s+/u', $line);
            $text = $isBullet ? preg_replace('/^\s*[-*•]\s+/u', '', $line) : $line;
            $type = $isBullet ? 'bullets' : 'text';

            if ($blocks && end($blocks)['type'] === $type) {
                $blocks[array_key_last($blocks)]['lines'][] = $text;
            } else {
                $blocks[] = ['type' => $type, 'lines' => [$text]];
            }
        }
    @endphp
    @foreach ($blocks as $block)
        @if ($block['type'] === 'bullets')
            <table class="item-bullets">
                @foreach ($block['lines'] as $bullet)
                    <tr><td class="dot">•</td><td>{{ $bullet }}</td></tr>
                @endforeach
            </table>
        @elseif (trim(implode('', $block['lines'])) !== '')
            <div class="item-detail">{!! nl2br(e(implode("\n", $block['lines']))) !!}</div>
        @endif
    @endforeach
@endif
