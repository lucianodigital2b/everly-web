<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Writes guest uploads to the media disk (Cloudflare R2 in production).
 *
 * Objects are written without an ACL: R2 rejects S3 ACL headers, and public
 * reads are served instead from the bucket's public domain (R2_URL), which is
 * what EventPhoto resolves its absolute URLs against.
 */
class MediaStorage
{
    public function __construct(private readonly ImageManager $images) {}

    /**
     * Store the original plus a generated thumbnail and return the persisted
     * photo. Videos are stored as-is — no thumbnail, since extracting a frame
     * needs ffmpeg, which isn't a dependency here.
     */
    public function storeUpload(Event $event, UploadedFile $file, ?int $guestId): EventPhoto
    {
        $disk = Storage::disk(config('everly.media.disk'));
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $base = 'events/'.$event->id.'/'.Str::uuid();
        $path = $base.'.'.Str::lower($extension);

        $disk->put($path, $file->getContent());

        $width = null;
        $height = null;
        $thumbnailPath = null;

        if (! $isVideo) {
            [$width, $height, $thumbnailPath] = $this->processImage($file, $base, $disk);
        }

        return $event->photos()->create([
            'guest_id' => $guestId,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'media_type' => $isVideo ? EventPhoto::TYPE_VIDEO : EventPhoto::TYPE_IMAGE,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Store an event cover and return the absolute URL to put on the event.
     *
     * Covers are the one image the *owner* uploads rather than a guest, and
     * they are only ever displayed as a card or a full-bleed backdrop — so
     * they are scaled down on the way in. An unreadable file is still stored
     * as-is: a cover that looks wrong beats a save that fails.
     */
    public function storeCover(Event $event, UploadedFile $file): string
    {
        $disk = Storage::disk(config('everly.media.disk'));
        $path = 'events/'.$event->id.'/covers/'.Str::uuid().'.jpg';

        try {
            $image = $this->images->read($file->getRealPath())
                ->scaleDown(
                    width: config('everly.media.cover_size'),
                    height: config('everly.media.cover_size'),
                )
                ->toJpeg(quality: 85);

            $disk->put($path, (string) $image);
        } catch (Throwable) {
            $extension = Str::lower($file->getClientOriginalExtension() ?: 'jpg');
            $path = 'events/'.$event->id.'/covers/'.Str::uuid().'.'.$extension;
            $disk->put($path, $file->getContent());
        }

        return $disk->url($path);
    }

    /**
     * @param  Filesystem  $disk
     * @return array{0: int|null, 1: int|null, 2: string|null}
     */
    private function processImage(UploadedFile $file, string $base, $disk): array
    {
        try {
            $image = $this->images->read($file->getRealPath());
        } catch (Throwable) {
            // An unreadable or exotic image still gets stored; it just won't
            // have dimensions or a thumbnail. Losing a guest's photo over a
            // failed resize would be the worse outcome.
            return [null, null, null];
        }

        $width = $image->width();
        $height = $image->height();

        $thumbnailPath = $base.'_thumb.jpg';

        $thumbnail = $image->scaleDown(
            width: config('everly.media.thumbnail_size'),
            height: config('everly.media.thumbnail_size'),
        )->toJpeg(quality: 82);

        $disk->put($thumbnailPath, (string) $thumbnail);

        return [$width, $height, $thumbnailPath];
    }
}
