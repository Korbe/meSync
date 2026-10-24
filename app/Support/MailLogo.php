<?php

namespace App\Support;

/**
 * The own logo in the header of a mail: embedded (cid:) so it shows without loading external
 * images, with a fixed pixel size. Outlook ignores max-width/max-height and would show the
 * image in its original size, so width and height are set as attributes.
 */
class MailLogo
{
    public const MAX_WIDTH = 200;

    public const MAX_HEIGHT = 56;

    /**
     * @return array{src: string, width: int, height: int}|null
     */
    public static function embed(mixed $message, ?string $path): ?array
    {
        if (! $message || ! $path || ! is_file($path)) {
            return null;
        }

        // The view renders twice (HTML + text): reuse the logo if it is already embedded
        $embedded = collect($message->getSymfonyMessage()->getAttachments())
            ->first(fn ($part) => $part->getFilename() === basename($path));
        $src = $embedded ? 'cid:'.$embedded->getContentId() : $message->embed($path);

        return ['src' => $src, ...self::size($path)];
    }

    /**
     * Scaled down to fit into the header box, never enlarged.
     *
     * @return array{width: int, height: int}
     */
    public static function size(string $path): array
    {
        $info = @getimagesize($path);

        // No raster image (e.g. SVG): a sensible width, the client keeps the ratio
        if (! $info || ! $info[0] || ! $info[1]) {
            return ['width' => self::MAX_WIDTH, 'height' => 0];
        }

        [$width, $height] = $info;
        $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);

        return ['width' => max(1, (int) round($width * $scale)), 'height' => max(1, (int) round($height * $scale))];
    }
}
