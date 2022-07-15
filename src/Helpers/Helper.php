<?php

namespace Beshkin\MicloudPhp\Helpers;

class Helper
{
    const SMOOTH_EFFECT = 'smooth';
    const SUDDEN_EFFECT = 'sudden';
    const DEFAULT_DURATION = 500;

    /**
     * @param $arguments
     * @param int $duration
     * @return array
     */
    static public function withLightEffect($arguments, int $duration = 0): array
    {
        $result = is_array($arguments) ? $arguments : [$arguments];
        if ($duration > 0) {
            $result[] = self::SMOOTH_EFFECT;
            $result[] = $duration;
        } else {
            $result[] = self::SUDDEN_EFFECT;
            $result[] = 0;
        }

        return $result;
    }
}
