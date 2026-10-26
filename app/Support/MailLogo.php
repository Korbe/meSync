<?php

namespace App\Support;

/**
 * The own logo in the header of a mail: embedded (cid:) so it shows without loading external
 * images, with a fixed pixel size. Outlook ignores max-width/max-height and would show the
 * image in its original size, so width and height are set as attributes.
 *
 * Dark mode: mail programs that darken the mail (new Outlook, Outlook web and apps) get the
 * logo with light lettering instead. Classic Outlook for Windows can't switch images: the
 * normal logo comes on a solid background like the header's, readable on its darkened header too.
 */
class MailLogo
{
    public const MAX_WIDTH = 200;

    public const MAX_HEIGHT = 56;

    /** Background of the mail header (theme .wrapper): the flattened logo blends in */
    public const HEADER_BACKGROUND = [245, 245, 245];

    /**
     * @return array{src: string, width: int, height: int, dark: ?array{src: string, width: int, height: int}}|null
     */
    public static function embed(mixed $message, ?string $path, ?string $darkPath = null): ?array
    {
        if (! $message || ! $path || ! is_file($path)) {
            return null;
        }

        $dark = $darkPath && is_file($darkPath) ? ['src' => self::attach($message, $darkPath), ...self::size($darkPath)] : null;

        // With a dark-mode logo the lettering is dark: the normal logo goes out on the solid
        // header background, readable also where the image can't be switched (classic Outlook).
        // Every embedded image is shown somewhere, else Outlook lists it as attachment.
        $flattened = $dark ? self::flattened($path) : null;

        return [
            'src' => self::attach($message, $flattened ?? $path),
            ...self::size($path),
            'dark' => $dark,
        ];
    }

    /**
     * The view renders twice (HTML + text): reuse an image that is already embedded.
     */
    private static function attach(mixed $message, string $path): string
    {
        $embedded = collect($message->getSymfonyMessage()->getAttachments())
            ->first(fn ($part) => $part->getFilename() === basename($path));

        return $embedded ? 'cid:'.$embedded->getContentId() : $message->embed($path);
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

    /**
     * The logo on the solid header background (twice the display size for sharp screens),
     * cached by file and change time. Null without GD or for unreadable images.
     */
    public static function flattened(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $size = self::size($path);
        if (! $size['height']) {
            return null;
        }

        $target = storage_path('app/mail-logos/'.md5($path.'|'.filemtime($path)).'-flat.png');
        if (is_file($target)) {
            return $target;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source) {
            return null;
        }

        [$width, $height] = [$size['width'] * 2, $size['height'] * 2];
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::HEADER_BACKGROUND));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        @mkdir(dirname($target), 0775, true);
        imagepng($canvas, $target);

        return $target;
    }
}
