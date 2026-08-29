<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected const MAX_AVATAR_SIZE_BYTES = 2 * 1024 * 1024;

    protected function rejectOversizedAvatar(Request $request, ?string $redirectTo = null): ?RedirectResponse
    {
        $avatar = $request->file('avatar');

        if ($avatar === null) {
            return null;
        }

        $size = $avatar->getSize();

        if (!$avatar->isValid() || !is_int($size) || $size > self::MAX_AVATAR_SIZE_BYTES) {
            $redirect = $redirectTo === null ? back() : redirect($redirectTo);

            return $redirect->with(
                'image_size_error',
                'The selected image must be 2 MB or less. Please choose a smaller image.'
            );
        }

        return null;
    }
}
