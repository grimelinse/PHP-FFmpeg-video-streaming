<?php

/**
 * This file is part of the PHP-FFmpeg-video-streaming package.
 *
 * (c) Amin Yazdanpanah <contact@aminyazdanpanah.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Streaming\Filters;


use Streaming\DASH;
use Streaming\Format\X264;
use Streaming\Representation;

class DASHFilter extends Filter
{
    /**
     * @param $media
     */
    public function setFilter($media): void
    {
        $this->filter = $this->DASHFilter($media);
    }

    /**
     * @param DASH $media
     * @return array
     */
    private function DASHFilter(DASH $media)
    {
        $filter = $this->getAdditionalFilters($media->getFormat(), count($media->getRepresentations()));

        foreach ($media->getRepresentations() as $key => $representation) {
            if ($representation instanceof Representation) {
                $filter[] = "-map";
                $filter[] = "0";
                $filter[] = "-b:v:" . $key;
                $filter[] = $representation->getKiloBitrate() . "k";

                if (null !== $representation->getResize()) {
                    $filter[] = "-s:v:" . $key;
                    $filter[] = $representation->getResize();
                }
            }
        }

        if ($media->getAdaption()) {
            $filter[] = "-adaptation_sets";
            $filter[] = $media->getAdaption();
        }

        return $filter;
    }

    /**
     * @param $format
     * @param $count
     * @return array
     */
    private function getAdditionalFilters($format, $count)
    {
        $filter = [
            "-bf", "1", "-keyint_min", "120", "-g", "120",
            "-sc_threshold", "0", "-b_strategy", "0",
            "-use_timeline", "1", "-use_template", "1", "-f", "dash"
        ];

        if ($format instanceof X264) {
            $filter[] = "-profile:v:0";
            $filter[] = "main";

            while ($count > 0) {
                $filter[] = "-profile:v:" . $count;
                $filter[] = "baseline";
                $count--;
            }
        }

        return $filter;
    }
}