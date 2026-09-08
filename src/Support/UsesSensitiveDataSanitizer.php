<?php

declare(strict_types=1);

namespace Doppar\Insight\Support;

trait UsesSensitiveDataSanitizer
{
    protected function sanitizer(): SensitiveDataSanitizer
    {
        if (function_exists('app')) {
            try {
                $sanitizer = app(SensitiveDataSanitizer::class);
                if ($sanitizer instanceof SensitiveDataSanitizer) {
                    return $sanitizer;
                }
            } catch (\Throwable) {
            }
        }

        return SensitiveDataSanitizer::fromConfig();
    }
}
