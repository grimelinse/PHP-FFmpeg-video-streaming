<?php

/**
 * This file is part of the PHP-FFmpeg-video-streaming package.
 *
 * (c) Amin Yazdanpanah <contact@aminyazdanpanah.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Streaming;

use FFMpeg\Exception\ExceptionInterface;
use Streaming\Clouds\Cloud;
use Streaming\Exception\InvalidArgumentException;
use Streaming\Exception\RuntimeException;
use Streaming\Filters\Filter;
use Streaming\Traits\Formats;


abstract class Export
{
    use Formats;

    /** @var object */
    protected $media;

    /** @var array */
    protected $path_info;

    /** @var string */
    protected $tmp_dir;

    /** @var string */
    protected $uri;

    /**
     * Export constructor.
     * @param Media $media
     */
    public function __construct(Media $media)
    {
        $this->media = $media;
        $this->path_info = pathinfo($media->getPath());
    }

    /**
     * @return object|Media
     */
    public function getMedia(): Media
    {
        return $this->media;
    }

    /**
     * @return bool
     */
    public function isTmpDir(): bool
    {
        return (bool)$this->tmp_dir;
    }

    /**
     * @return array
     */
    public function getPathInfo(): array
    {
        return $this->path_info;
    }

    /**
     * @param string|null $path
     */
    private function moveTmp(?string $path): void
    {
        if ($this->isTmpDir() && !is_null($path)) {
            File::move($this->tmp_dir, dirname($path));
            $this->path_info = pathinfo($path);
            $this->tmp_dir = '';
        }
    }

    /**
     * @param array $clouds
     * @param string $path
     */
    private function clouds(array $clouds, ?string $path): void
    {
        if (!empty($clouds)) {
            Cloud::uploadDirectory($clouds, $this->tmp_dir);
            $this->moveTmp($path);
        }
    }

    /**
     * @return string
     */
    protected function getFilePath(): string
    {
        return str_replace("\\", "/", $this->path_info["dirname"] . "/" . $this->path_info["filename"]);
    }

    /**
     * @return string
     */
    abstract protected function getPath(): string;

    /**
     * @return Filter
     */
    abstract protected function getFilter(): Filter;

    /**
     * Run FFmpeg to package media content
     */
    private function run(): void
    {
        try {
            $this->media
                ->addFilter($this->getFilter())
                ->save($this->getFormat(), $this->getPath());
        } catch (ExceptionInterface $e) {
            throw new RuntimeException("An error occurred while saving files: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string|null $path
     */
    private function tmpDirectory(?string $path): void
    {
        $basename = $path ? basename($path) : $this->path_info['basename'];

        $this->tmp_dir = File::tmpDir();
        $this->path_info = pathinfo($this->tmp_dir . $basename);
    }

    /**
     * @param $path
     * @param $clouds
     */
    private function makePaths(?string $path, array $clouds): void
    {
        if (!empty($clouds)) {
            $this->tmpDirectory($path);
        } elseif (!is_null($path)) {
            if (strlen($path) > PHP_MAXPATHLEN) {
                throw new InvalidArgumentException("The path is too long");
            }

            File::makeDir(dirname($path));
            $this->path_info = pathinfo($path);
        } elseif ($this->media->isTmp()) {
            throw new InvalidArgumentException("You need to specify a path. It is not possible to save to a tmp directory");
        }
    }

    /**
     * @param string $path
     * @param array $clouds
     * @return mixed
     */
    public function save(string $path = null, array $clouds = [])
    {
        $this->makePaths($path, $clouds);
        $this->run();
        $this->clouds($clouds, $path);

        return $this;
    }

    /**
     * @param string $url
     */
    public function live(string $url): void
    {
        $this->uri = $url;
        $this->path_info = pathinfo($url);
        $this->run();
    }

    /**
     * @return Metadata
     */
    public function metadata(): Metadata
    {
        return new Metadata($this);
    }

    /**
     * clear tmp files
     */
    public function __destruct()
    {
        // make sure that FFmpeg process has benn terminated
        sleep(1);

        if ($this->media->isTmp()) {
            File::remove($this->media->getPath());
        }

        if ($this->tmp_dir) {
            File::remove($this->tmp_dir);
        }

        if ($this instanceof HLS && $this->tmp_key_info_file) {
            File::remove($this->getHlsKeyInfoFile());
        }
    }
}