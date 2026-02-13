<?php

namespace Echo\Framework\Admin\Schema;

/**
 * Bootstrap 5 modal size variants.
 */
enum ModalSize: string
{
    case Small = 'modal-sm';
    case Default = '';
    case Large = 'modal-lg';
    case ExtraLarge = 'modal-xl';
    case Fullscreen = 'modal-fullscreen';
}
