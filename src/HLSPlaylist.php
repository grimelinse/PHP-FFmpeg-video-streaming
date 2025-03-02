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


class HLSPlaylist
{
    /**
     * @param string $filename
     * @param array $reps
     * @param string $manifests
     * @param array $stream_info
     */
    public static function save(string $filename, array $reps, string $manifests, array $stream_info = []): void
    {
        file_put_contents($filename, static::contents($reps, $manifests, $stream_info));
    }

    /**
     * @param array $reps
     * @param string $manifests
     * @param array $stream_info
     * @return string
     */
    private static function contents(array $reps, string $manifests, array $stream_info = []): string
    {
        $content = ["#EXTM3U", "#EXT-X-VERSION:3"];
        foreach ($reps as $rep) {
            $content[] = static::streamInfo($rep, $stream_info);
            $content[] = $manifests . "_" . $rep->getHeight() . "p.m3u8";
        }

        return implode(PHP_EOL, $content);
    }

    /**
     * @param Representation $rep
     * @param array $extra
     * @return string
     */
    private static function streamInfo(Representation $rep, array $extra = []): string
    {
        $ext_stream = '#EXT-X-STREAM-INF:';
        $params = [
            "BANDWIDTH=" . $rep->getKiloBitrate() * 1024,
            "RESOLUTION=" . $rep->getResize(),
            "NAME=\"" . $rep->getHeight() . "\""
        ];

        return $ext_stream . implode(",", array_merge($params, $extra));
    }
}