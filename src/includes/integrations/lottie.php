<?php

//https://docs.lottiefiles.com/lottie-player/components/lottie-player/properties

/**
 * Generates HTML for a Lottie animation player with configurable attributes
 * 
 * @param int $autoplay Whether to autoplay the animation (1|0)
 * @param string $background Background color (default: 'transparent')
 * @param int $controls Whether to show controls (1|0)
 * @param string $count Reserved for future use
 * @param int $direction Animation direction (1 for normal, -1 for reverse)
 * @param int $disableCheck Whether to disable checks (1|0)
 * @param int $hover Whether to play on hover (1|0)
 * @param int $intermission Pause between loops in milliseconds
 * @param int|null $id Optional ID for the player element
 * @param int|string $height Height of the player (e.g., 100 or '100px' or '100%')
 * @param int $loop Whether to loop the animation (1|0)
 * @param string $mode Play mode ('normal', 'bounce', etc.)
 * @param string $renderer Renderer to use ('svg', 'canvas', etc.)
 * @param float $speed Playback speed (1.0 = normal)
 * @param string $src URL/path to the Lottie JSON file
 * @param int|string $width Width of the player (e.g., 100 or '100px' or '100%')
 * @return string HTML markup for the Lottie player
 */
function plura_lottie(
    int $autoplay = 1,
    string $background = 'transparent',
    int $controls = 0,
    string $count = '',
    int $direction = 1,
    int $disableCheck = 0,
    int $hover = 0,
    int $intermission = 1,
    ?int $id = null,
    int|string $height = '100%',
    int $loop = 0,
    string $mode = 'normal',
    string $renderer = 'svg',
    float $speed = 1,
    string $src = '',
    int|string $width = '100%'
): string {
    $atts = [
        'background' => $background,
        'speed' => $speed,
        'src' => $src
    ];

    // Set boolean attributes
    if ($autoplay) $atts['autoplay'] = true;
    if ($controls) $atts['controls'] = true;
    if ($loop) $atts['loop'] = true;
    if ($hover) $atts['hover'] = true;

    // Set optional ID
    if ($id !== null) {
        $atts['id'] = (string) $id;
    }

    // Process dimensions
    $style = [];
    foreach (['width' => $width, 'height' => $height] as $dim => $value) {
        if ($value) {
            $style[] = $dim . ':' . match(true) {
                is_int($value) => $value . 'px',
                is_string($value) && ctype_digit($value) => $value . 'px',
                default => $value
            };
        }
    }

    // Add style if we have dimensions
    if (!empty($style)) {
        $atts['style'] = implode('; ', $style);
    }

    return sprintf(
        '<lottie-player %s></lottie-player>',
        plura_attributes($atts)
    );
}