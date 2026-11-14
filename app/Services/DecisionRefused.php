<?php

namespace App\Services;

use RuntimeException;

/**
 * An admin action that cannot be done in the current state, for example a
 * second approval of the same milestone. The message is shown to the admin.
 */
class DecisionRefused extends RuntimeException {}
